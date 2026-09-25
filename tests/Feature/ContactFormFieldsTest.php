<?php

namespace Tests\Feature;

use App\Cms\Builder\Blocks\ContactFormBlock;
use App\Cms\Forms\ContactFormSchema;
use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The Contact form widget's own fields, and whether the endpoint holds to
 * what the form declared.
 *
 * The form and the endpoint are separate: anyone can post to the endpoint
 * without ever loading the form. So the tests that matter here are the ones
 * where the request disagrees with the schema - a required answer left out, a
 * field nobody asked for, a rewritten token - and the server wins.
 */
class ContactFormFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->get('/');
    }

    /** The schema a set of widget settings describes, as a token would carry it. */
    private function token(array $settings): string
    {
        return ContactFormSchema::token(ContactFormSchema::fromSettings($settings));
    }

    public function test_extra_fields_are_collected_and_stored_against_their_labels(): void
    {
        $token = $this->token([
            'show_phone' => false,
            'show_subject' => false,
            'extra_fields' => [
                ['label' => 'Company name', 'type' => 'text', 'required' => true],
                ['label' => 'How did you hear about us?', 'type' => 'select', 'options' => "A friend\nSearch"],
            ],
        ]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
            'custom' => [
                'company_name' => 'Menon Traders',
                'how_did_you_hear_about_us' => 'A friend',
            ],
        ])->assertSessionHasNoErrors();

        $submission = ContactSubmission::sole();

        $this->assertSame([
            ['label' => 'Company name', 'value' => 'Menon Traders'],
            ['label' => 'How did you hear about us?', 'value' => 'A friend'],
        ], $submission->extra);
    }

    public function test_a_required_extra_field_is_refused_when_it_is_blank(): void
    {
        $token = $this->token([
            'extra_fields' => [['label' => 'Company name', 'type' => 'text', 'required' => true]],
        ]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
        ])->assertSessionHasErrors('custom.company_name');

        $this->assertSame(0, ContactSubmission::count());
    }

    public function test_an_optional_extra_field_may_be_left_out(): void
    {
        $token = $this->token([
            'extra_fields' => [['label' => 'Company name', 'type' => 'text']],
        ]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
        ])->assertSessionHasNoErrors();

        $this->assertNull(ContactSubmission::sole()->extra);
    }

    public function test_a_field_the_form_never_asked_for_is_ignored(): void
    {
        $token = $this->token(['extra_fields' => []]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
            'custom' => ['smuggled' => 'buy cheap pills'],
        ])->assertSessionHasNoErrors();

        $this->assertNull(ContactSubmission::sole()->extra);
    }

    public function test_a_built_in_field_can_be_made_required(): void
    {
        $token = $this->token(['show_phone' => true, 'require_phone' => true]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
        ])->assertSessionHasErrors('phone');
    }

    public function test_a_built_in_field_can_be_made_optional(): void
    {
        $token = $this->token(['require_name' => false, 'require_message' => false]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'email' => 'asha@example.test',
        ])->assertSessionHasNoErrors();

        $this->assertSame('', ContactSubmission::sole()->name);
    }

    public function test_the_email_address_stays_required_however_the_schema_is_written(): void
    {
        $forged = ContactFormSchema::token(['fields' => [
            ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => false],
        ]]);

        $this->post('/contact', [ContactFormSchema::TOKEN_INPUT => $forged])
            ->assertSessionHasErrors('email');
    }

    public function test_a_dropdown_answer_must_be_one_of_the_choices_offered(): void
    {
        $token = $this->token([
            'extra_fields' => [['label' => 'Department', 'type' => 'select', 'options' => "Sales\nSupport"]],
        ]);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
            'custom' => ['department' => 'Accounts'],
        ])->assertSessionHasErrors('custom.department');
    }

    public function test_a_rewritten_token_falls_back_to_the_built_in_form(): void
    {
        // Encrypted with a key that is not this application's, so it decrypts
        // to nothing here and must not be believed.
        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => 'eyJpdiI6Im5vdCByZWFsIiwidmFsdWUiOiJub3QgcmVhbCJ9',
            'email' => 'asha@example.test',
        ])->assertSessionHasErrors(['name', 'message']);
    }

    public function test_a_form_that_sends_no_token_keeps_the_rules_it_always_had(): void
    {
        // A theme's own contact page, which knows nothing about this.
        $this->post('/contact', [
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'phone' => '9876543210',
            'subject' => 'Quote',
            'message' => 'We would like a quote for forty units.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('9876543210', ContactSubmission::sole()->phone);
    }

    public function test_the_message_the_form_shows_after_sending_travels_with_it(): void
    {
        $token = $this->token(['success_text' => 'Got it. Our team will call you.']);

        $this->post('/contact', [
            ContactFormSchema::TOKEN_INPUT => $token,
            'name' => 'Asha Menon',
            'email' => 'asha@example.test',
            'message' => 'We would like a quote for forty units.',
        ])->assertSessionHas('status', 'Got it. Our team will call you.');
    }

    public function test_two_fields_sharing_a_label_get_separate_answers(): void
    {
        $schema = ContactFormSchema::fromSettings([
            'extra_fields' => [
                ['label' => 'Reference', 'type' => 'text'],
                ['label' => 'Reference', 'type' => 'text'],
            ],
        ]);

        $keys = array_column(array_filter($schema['fields'], fn ($f) => $f['extra']), 'key');

        $this->assertSame(['reference', 'reference_2'], $keys);
    }

    public function test_an_extra_field_cannot_take_over_a_built_in_one(): void
    {
        $schema = ContactFormSchema::fromSettings([
            'extra_fields' => [['label' => 'Email', 'type' => 'text', 'required' => false]],
        ]);

        $email = collect($schema['fields'])->firstWhere('key', 'email');

        $this->assertTrue($email['required']);
        $this->assertFalse($email['extra']);
    }

    public function test_the_rendered_widget_carries_its_schema_and_marks_required_fields(): void
    {
        $html = (new ContactFormBlock)->render([
            'show_phone' => false,
            'show_subject' => false,
            'extra_fields' => [['label' => 'Company name', 'type' => 'text', 'required' => true]],
        ], ['id' => 'abc123']);

        $this->assertStringContainsString('name="custom[company_name]"', $html);
        $this->assertStringContainsString('name="'.ContactFormSchema::TOKEN_INPUT.'"', $html);
        $this->assertStringNotContainsString('name="phone"', $html);

        preg_match('/name="'.ContactFormSchema::TOKEN_INPUT.'" value="([^"]+)"/', $html, $matches);

        $carried = json_decode(Crypt::decryptString(html_entity_decode($matches[1])), true);

        $this->assertSame(
            ['name', 'email', 'company_name', 'message'],
            array_column($carried['fields'], 'key')
        );
    }
}
