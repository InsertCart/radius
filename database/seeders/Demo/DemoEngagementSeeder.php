<?php

namespace Database\Seeders\Demo;

use App\Models\ActivityLog;
use App\Models\ContactSubmission;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

/**
 * The things that arrive rather than the things you publish: contact
 * messages, newsletter signups and the admin audit trail.
 *
 * Between them they fill the dashboard's to-do list and its activity feed,
 * both of which otherwise sit empty on a site nobody has used yet - which is
 * exactly the state a demo is supposed to avoid showing.
 *
 * Each row is keyed on something stable (an email and subject, an address, a
 * description) so a second run rewrites the same records, and `cms:demo
 * --remove` can delete precisely these and leave real messages alone.
 */
class DemoEngagementSeeder extends Seeder
{
    public function run(): void
    {
        if (modules()->enabled('contact')) {
            $this->contactSubmissions();
        }

        if (modules()->enabled('newsletter')) {
            $this->subscribers();
        }

        $this->activity();
    }

    /**
     * Enquiries in every state the inbox filters on, weighted towards unread
     * so the 'needs your attention' panel has something to point at.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function submissions(): array
    {
        return [
            ['name' => 'Rachel Odum', 'email' => 'rachel.odum@demo.invalid', 'phone' => '+44 7700 900318',
                'subject' => 'Wholesale pricing for a 12kg monthly order',
                'message' => "We run a two-site cafe in Leeds and get through roughly 12kg a month, mostly of a blend. Do you do wholesale bags and standing orders, and is there a trade price list I can look at?\n\nHappy to come to you for a cupping if that is easier.",
                'days' => 0, 'read' => false],
            ['name' => 'Tobias Lund', 'email' => 'tobias.lund@demo.invalid', 'phone' => null,
                'subject' => 'Grinder recommendation for a small office',
                'message' => "There are nine of us and we currently fight over a blade grinder. Would the hand grinder cope or should we be looking at the electric one? Budget is not really the issue, counter space is.",
                'days' => 0, 'read' => false],
            ['name' => 'Meera Iyer', 'email' => 'meera.iyer@demo.invalid', 'phone' => '+91 98860 21174',
                'subject' => 'Order arrived with a split bag',
                'message' => "One of the two bags in my order had split along the seam and there were beans loose in the box. The other one was perfect. Not looking for a refund, just letting you know in case it is a batch problem with the packaging.",
                'days' => 1, 'read' => false],
            ['name' => 'Sam Oduya', 'email' => 'sam.oduya@demo.invalid', 'phone' => null,
                'subject' => 'Do you ship to Kenya?',
                'message' => "Slightly odd request given where the coffee comes from, but I am moving to Nairobi in October and would like to keep the subscription going. Do you ship there, and roughly what does it cost?",
                'days' => 2, 'read' => false],
            ['name' => 'Beth Carrow', 'email' => 'beth.carrow@demo.invalid', 'phone' => '+44 7700 900982',
                'subject' => 'Gift subscription for three months',
                'message' => "I would like to send someone a bag a month for three months, starting in December, with a note in the first box. Is that something you can set up, and can I pay for the whole thing up front?",
                'days' => 3, 'read' => false],
            ['name' => 'Andre Bassi', 'email' => 'andre.bassi@demo.invalid', 'phone' => null,
                'subject' => 'Decaf process question',
                'message' => "The listing says sugarcane process for the decaf. Is that the same as ethyl acetate, or something different? Asking because a family member reacts badly to solvent-decaffeinated coffee.",
                'days' => 5, 'read' => true],
            ['name' => 'Claire Ng', 'email' => 'claire.ng@demo.invalid', 'phone' => '+61 400 552 118',
                'subject' => 'Missing item from order',
                'message' => "The filters were on the invoice but not in the box. Everything else was there. No rush on it, but could you send them with the next order rather than separately?",
                'days' => 8, 'read' => true],
            ['name' => 'Patrick Dunne', 'email' => 'patrick.dunne@demo.invalid', 'phone' => null,
                'subject' => 'Cupping sessions - are they open to the public?',
                'message' => "Saw on the blog that you cup every new lot on a Thursday. Is that ever open to customers, or is it strictly a staff exercise? Would happily come along and keep quiet in a corner.",
                'days' => 12, 'read' => true],
            ['name' => 'Ingrid Sollis', 'email' => 'ingrid.sollis@demo.invalid', 'phone' => '+46 70 998 22 11',
                'subject' => 'Invoice copy for expenses',
                'message' => "Could you send a VAT invoice for my last order? Our finance team will not accept the order confirmation email on its own.",
                'days' => 16, 'read' => true],
            ['name' => 'Global SEO Partners', 'email' => 'outreach@demo.invalid', 'phone' => null,
                'subject' => 'RANK #1 ON GOOGLE - GUARANTEED RESULTS',
                'message' => "Dear Sir/Madam, I reviewed your website and found 37 critical SEO errors. Our agency can fix all of them and guarantee first page rankings within 30 days. Reply for a free audit!!!",
                'days' => 19, 'read' => true],
        ];
    }

    /**
     * A mailing list with the shape a real one has: mostly active, a few
     * opt-outs, and an older block that came in from an import.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function subscriberList(): array
    {
        return [
            ['email' => 'rachel.odum@demo.invalid', 'name' => 'Rachel Odum', 'status' => 'subscribed', 'source' => 'website', 'days' => 0],
            ['email' => 'tobias.lund@demo.invalid', 'name' => 'Tobias Lund', 'status' => 'subscribed', 'source' => 'website', 'days' => 1],
            ['email' => 'hello.frida@demo.invalid', 'name' => 'Frida Almqvist', 'status' => 'subscribed', 'source' => 'checkout', 'days' => 2],
            ['email' => 'nitin.rao@demo.invalid', 'name' => 'Nitin Rao', 'status' => 'subscribed', 'source' => 'import', 'days' => 3],
            ['email' => 'k.brennan@demo.invalid', 'name' => 'Kate Brennan', 'status' => 'subscribed', 'source' => 'website', 'days' => 4],
            ['email' => 'meera.iyer@demo.invalid', 'name' => 'Meera Iyer', 'status' => 'subscribed', 'source' => 'checkout', 'days' => 6],
            ['email' => 'dan.mcallister@demo.invalid', 'name' => 'Dan McAllister', 'status' => 'subscribed', 'source' => 'blog', 'days' => 9],
            ['email' => 'sofia.bianchi@demo.invalid', 'name' => 'Sofia Bianchi', 'status' => 'subscribed', 'source' => 'website', 'days' => 12],
            ['email' => 'p.ashworth@demo.invalid', 'name' => 'Paul Ashworth', 'status' => 'unsubscribed', 'source' => 'import', 'days' => 15],
            ['email' => 'lucia.ferreira@demo.invalid', 'name' => 'Lucia Ferreira', 'status' => 'subscribed', 'source' => 'checkout', 'days' => 18],
            ['email' => 'george.ito@demo.invalid', 'name' => 'George Ito', 'status' => 'subscribed', 'source' => 'blog', 'days' => 22],
            ['email' => 'h.oyelaran@demo.invalid', 'name' => 'Hakeem Oyelaran', 'status' => 'unsubscribed', 'source' => 'website', 'days' => 26],
            ['email' => 'mira.solberg@demo.invalid', 'name' => 'Mira Solberg', 'status' => 'subscribed', 'source' => 'checkout', 'days' => 31],
            ['email' => 'jon.kearns@demo.invalid', 'name' => 'Jon Kearns', 'status' => 'subscribed', 'source' => 'website', 'days' => 38],
            ['email' => 'abi.tunde@demo.invalid', 'name' => 'Abi Tunde', 'status' => 'subscribed', 'source' => 'blog', 'days' => 44],
            ['email' => 'r.castellanos@demo.invalid', 'name' => 'Rosa Castellanos', 'status' => 'unsubscribed', 'source' => 'import', 'days' => 52],
            ['email' => 'wei.zhang@demo.invalid', 'name' => 'Wei Zhang', 'status' => 'subscribed', 'source' => 'checkout', 'days' => 60],
            ['email' => 'e.vandenberg@demo.invalid', 'name' => 'Els van den Berg', 'status' => 'subscribed', 'source' => 'website', 'days' => 71],
            ['email' => 'tariq.aziz@demo.invalid', 'name' => 'Tariq Aziz', 'status' => 'subscribed', 'source' => 'blog', 'days' => 84],
            ['email' => 'nell.harrow@demo.invalid', 'name' => 'Nell Harrow', 'status' => 'subscribed', 'source' => 'website', 'days' => 96],
        ];
    }

    /**
     * The audit trail. Descriptions are the key, so they have to stay unique
     * and readable - they are what the dashboard feed actually prints.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function activityEntries(): array
    {
        return [
            ['action' => 'order.status', 'user' => 'admin', 'hours' => 2,
                'subject' => ['order', 0],
                'description' => 'Marked an order as shipped and added a tracking number'],
            ['action' => 'product.updated', 'user' => 'admin', 'hours' => 5,
                'subject' => ['product', 'paper-filters-100'],
                'description' => 'Updated stock on Paper Filters, 100 pack'],
            ['action' => 'comment.approved', 'user' => 'ava.mercer@demo.invalid', 'hours' => 9,
                'description' => 'Approved a comment on Dial in espresso in five minutes'],
            ['action' => 'order.refund', 'user' => 'admin', 'hours' => 22,
                'description' => 'Refunded an order in full after a damaged delivery'],
            ['action' => 'post.published', 'user' => 'theo.lang@demo.invalid', 'hours' => 27,
                'subject' => ['post', null],
                'description' => 'Published a new blog post'],
            ['action' => 'product.created', 'user' => 'admin', 'hours' => 34,
                'subject' => ['product', 'spring-seasonal'],
                'description' => 'Created Spring Seasonal as a draft product'],
            ['action' => 'coupon.created', 'user' => 'admin', 'hours' => 49,
                'description' => 'Created the BEANS15 discount code'],
            ['action' => 'settings.updated', 'user' => 'admin', 'hours' => 58,
                'description' => 'Changed the low stock threshold in shop settings'],
            ['action' => 'media.uploaded', 'user' => 'ava.mercer@demo.invalid', 'hours' => 71,
                'description' => 'Uploaded 4 images to the media library'],
            ['action' => 'comment.spam', 'user' => 'theo.lang@demo.invalid', 'hours' => 88,
                'description' => 'Marked a comment as spam'],
            ['action' => 'user.login', 'user' => 'admin', 'hours' => 96,
                'description' => 'Signed in from a new device'],
            ['action' => 'menu.updated', 'user' => 'admin', 'hours' => 120,
                'description' => 'Reordered the main navigation menu'],
        ];
    }

    /** @return array<int, string> */
    public static function submissionEmails(): array
    {
        return array_column(self::submissions(), 'email');
    }

    /** @return array<int, string> */
    public static function subscriberEmails(): array
    {
        return array_column(self::subscriberList(), 'email');
    }

    /** @return array<int, string> */
    public static function activityDescriptions(): array
    {
        return array_column(self::activityEntries(), 'description');
    }

    private function contactSubmissions(): void
    {
        foreach (self::submissions() as $data) {
            $receivedAt = now()->subDays($data['days'])->subMinutes(random_int(20, 700));

            $submission = ContactSubmission::updateOrCreate(
                ['email' => $data['email'], 'subject' => $data['subject']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'message' => $data['message'],
                    'status' => $data['read'] ? 'read' : 'new',
                    'read_at' => $data['read'] ? $receivedAt->copy()->addHours(random_int(1, 20)) : null,
                    'ip_address' => '203.0.113.'.random_int(2, 250),
                ]
            );

            $submission->forceFill(['created_at' => $receivedAt, 'updated_at' => $receivedAt])->saveQuietly();
        }
    }

    private function subscribers(): void
    {
        foreach (self::subscriberList() as $data) {
            $joinedAt = now()->subDays($data['days'])->subMinutes(random_int(10, 600));

            $subscriber = Subscriber::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'source' => $data['source'],
                    'ip_address' => '203.0.113.'.random_int(2, 250),
                    'confirmed_at' => $joinedAt->copy()->addMinutes(random_int(2, 90)),
                ]
            );

            $subscriber->forceFill(['created_at' => $joinedAt, 'updated_at' => $joinedAt])->saveQuietly();
        }
    }

    private function activity(): void
    {
        $staff = $this->staff();

        foreach (self::activityEntries() as $data) {
            $happenedAt = now()->subHours($data['hours']);
            $subject = $this->subject($data['subject'] ?? null);

            $log = ActivityLog::updateOrCreate(
                ['description' => $data['description']],
                [
                    'user_id' => $staff[$data['user']] ?? null,
                    'action' => $data['action'],
                    'subject_type' => $subject ? $subject::class : null,
                    'subject_id' => $subject?->getKey(),
                    'ip_address' => '198.51.100.'.random_int(2, 250),
                    'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                ]
            );

            $log->forceFill(['created_at' => $happenedAt, 'updated_at' => $happenedAt])->saveQuietly();
        }
    }

    /**
     * The people the log entries are attributed to: the demo editors, plus
     * whichever admin account this site was installed with.
     *
     * @return array<string, int|null>
     */
    private function staff(): array
    {
        $ids = User::whereIn('email', array_column(DemoBlogSeeder::authors(), 'email'))
            ->pluck('id', 'email')
            ->all();

        $ids['admin'] = User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');

        return $ids;
    }

    /**
     * Resolve the record a log entry points at, if it is installed. A log
     * line with a dangling subject still renders, so a missing one is not
     * worth failing over.
     *
     * @param  array{0: string, 1: string|int|null}|null  $subject
     */
    private function subject(?array $subject): ?Model
    {
        if ($subject === null) {
            return null;
        }

        [$type, $key] = $subject;

        return match ($type) {
            'product' => Product::where('slug', $key)->first(),
            'post' => Post::whereIn('slug', DemoBlogSeeder::postSlugs())->latest('published_at')->first(),
            'order' => Order::where('order_number', 'like', DemoOrderSeeder::PREFIX.'%')->latest('created_at')->first(),
            default => null,
        };
    }
}
