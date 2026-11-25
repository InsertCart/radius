<?php

namespace Tests\Feature;

use App\Cms\Builder\Blocks\ContactFormBlock;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactProtectionTest extends TestCase
{
    use RefreshDatabase;

    private array $message = [
        'name' => 'Contact visitor', 'email' => 'visitor@example.test', 'message' => 'Please send a quotation.',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/');
        settings()->set('site_email', 'owner@example.test');
        Mail::spy();
    }

    private function solution(string $html): array
    {
        $this->assertSame(1, preg_match('/name="_contact_challenge" value="([^"]+)"/', $html, $token));
        $this->assertSame(1, preg_match('/What is (\d+) \+ (\d+)\?/', $html, $question));

        return ['_contact_challenge' => $token[1], 'contact_answer' => (string) ($question[1] + $question[2])];
    }

    public function test_a_correct_answer_saves_and_notifies_once_even_if_the_form_is_posted_twice(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();
        $post = $this->message + $this->solution($html);
        foreach ([1, 2] as $attempt) {
            $this->from('/contact')->post('/contact', $post)->assertRedirect('/contact')->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'Thanks for getting in touch. We will reply shortly.');
        }

        $this->assertSame($this->message['email'], ContactSubmission::sole()->email);
        Mail::shouldHaveReceived('raw')->once();
    }

    public function test_the_visible_button_is_the_default_submit_on_each_installed_contact_page(): void
    {
        themes()->sync();
        foreach (glob(base_path('themes/*/views/contact.blade.php')) as $template) {
            $slug = basename(dirname($template, 2));
            $this->assertTrue(themes()->preview($slug), $slug);
            $html = $this->get('/contact')->assertOk()->getContent();
            $document = new \DOMDocument;
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
            $xpath = new \DOMXPath($document);
            $form = $xpath->query('//form[@action="'.route('contact.submit').'"]')->item(0);
            $this->assertNotNull($form, $slug);
            $this->assertSame(1, $xpath->query('.//input[@name="contact_answer" and @required]', $form)->length, $slug);
            $buttons = $xpath->query('.//button[not(@type) or @type="submit"]', $form);
            $this->assertGreaterThanOrEqual(2, $buttons->length, $slug);
            $this->assertNotSame('_contact_copy', $buttons->item(0)->getAttribute('name'), $slug);
            $this->assertSame(1, $xpath->query('.//*[@hidden]//button[@name="_contact_copy" and @tabindex="-1"]', $form)->length, $slug);
        }
    }

    public function test_wrong_answer_shows_a_new_question_and_preserves_the_message_for_retry(): void
    {
        $post = $this->solution($this->get('/contact')->assertOk()->getContent());
        $post['contact_answer'] = '99';
        $this->from('/contact')->post('/contact', $this->message + $post)->assertSessionHasErrors('contact_answer');
        $this->assertDatabaseCount('contact_submissions', 0);
        Mail::shouldNotHaveReceived('raw');

        $retry = $this->get('/contact')->assertOk()->assertSeeText('That answer was incorrect.')
            ->assertSee($this->message['message']);
        $new = $this->solution($retry->getContent());
        $this->assertNotSame($post['_contact_challenge'], $new['_contact_challenge']);
        $this->post('/contact', $this->message + $new)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_each_rendered_form_is_independent_and_the_cache_cannot_share_a_visitors_question(): void
    {
        settings()->set('cache_enabled', true);
        $first = $this->solution($this->get('/contact')->assertHeaderMissing('X-Page-Cache')->getContent());
        $widget = (new ContactFormBlock)->render(['success_text' => 'We received your request.'], ['id' => 'quote']);
        $second = $this->solution($widget);
        preg_match('/name="_schema" value="([^"]+)"/', $widget, $schema);
        $this->assertNotSame($first['_contact_challenge'], $second['_contact_challenge']);

        $this->post('/contact', $this->message + $second + ['_form' => 'quote', '_schema' => $schema[1]])
            ->assertSessionHas('contact_form', 'quote')->assertSessionHas('status', 'We received your request.');
        $this->flushSession();
        $this->post('/contact', $this->message + $first)->assertSessionHasErrors('contact_answer');
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_expired_or_unanswered_questions_cannot_save_or_notify(): void
    {
        $post = $this->solution($this->get('/contact')->assertOk()->getContent());
        $this->post('/contact', $this->message + ['_contact_challenge' => $post['_contact_challenge']])
            ->assertSessionHasErrors('contact_answer');
        $this->travel(3)->hours();
        $this->post('/contact', $this->message + $post)->assertSessionHasErrors('contact_answer');
        $this->assertDatabaseCount('contact_submissions', 0);
        Mail::shouldNotHaveReceived('raw');
    }

    public function test_direct_posts_and_both_decoys_get_the_same_receipt_without_saving_or_emailing(): void
    {
        $widget = (new ContactFormBlock)->render(['success_text' => 'Thanks for your request.'], ['id' => 'quote']);
        $solution = $this->solution($widget);
        preg_match('/name="_schema" value="([^"]+)"/', $widget, $schema);
        $receipt = ['_schema' => $schema[1], '_form' => 'quote'];
        foreach ([[], ['website' => 'example.test'] + $solution, ['_contact_copy' => '1'] + $solution] as $trap) {
            $this->from('/contact')->post('/contact', $this->message + $receipt + $trap)
                ->assertRedirect('/contact')->assertSessionHasNoErrors()->assertSessionHas('contact_form', 'quote')
                ->assertSessionHas('status', 'Thanks for your request.');
        }
        $this->assertDatabaseCount('contact_submissions', 0);
        Mail::shouldNotHaveReceived('raw');
    }

    public function test_disabling_math_keeps_the_decoy_and_optional_recaptcha_working(): void
    {
        settings()->set('contact_math_enabled', false);
        settings()->set('recaptcha_enabled', true);
        settings()->set('recaptcha_site_key', 'site');
        settings()->set('recaptcha_secret_key', 'secret');
        Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9])]);
        $this->get('/contact')->assertOk()->assertDontSee('name="contact_answer"', false)->assertSee('name="_contact_copy"', false);
        $this->post('/contact', ['_contact_copy' => '1'])->assertSessionHas('status');
        $this->post('/contact', $this->message)->assertSessionHasErrors('recaptcha');
        $this->assertDatabaseCount('contact_submissions', 0);
        $this->post('/contact', $this->message + ['g-recaptcha-response' => 'human'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_only_staff_can_bulk_delete_and_only_selected_messages_are_removed(): void
    {
        $first = ContactSubmission::create($this->message);
        $second = ContactSubmission::create($this->message);
        $keep = ContactSubmission::create($this->message);
        $action = route('admin.contact.bulk-destroy');
        $visitor = User::create([
            'name' => 'Visitor', 'email' => 'visitor@example.test', 'password' => 'password',
            'role' => User::ROLE_CUSTOMER, 'status' => 'active',
        ]);
        $this->actingAs($visitor)->delete($action, ['ids' => [$first->id]])->assertForbidden();
        $visitor->update(['role' => User::ROLE_ADMIN]);
        $index = route('admin.contact.index', ['unread' => 1]);
        $this->get($index)->assertOk()->assertSeeText('Select all on this page')->assertSeeText('Delete selected');
        $this->from($index)->delete($action)->assertSessionHasErrors('ids');
        $this->delete($action, ['ids' => [$first->id, 'bad']])->assertSessionHasErrors('ids.1');
        $this->assertDatabaseCount('contact_submissions', 3);
        $this->delete($action, ['ids' => [$first->id, $second->id]])->assertRedirect($index)
            ->assertSessionHas('status', 'Deleted 2 messages.');
        $this->assertTrue(ContactSubmission::sole()->is($keep));
    }
}
