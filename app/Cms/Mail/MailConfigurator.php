<?php

namespace App\Cms\Mail;

use App\Cms\Settings\SettingsRepository;

/**
 * Applies the site owner's email settings to Laravel's mail config at runtime.
 *
 * SMTP details live in the database because a buyer changes them from the
 * admin panel. API keys for Resend, SES and Postmark deliberately stay in
 * .env: they are high-value credentials, and keeping them out of the database
 * means a SQL-injection or a leaked backup does not hand them over.
 */
class MailConfigurator
{
    public function __construct(private SettingsRepository $settings) {}

    public function apply(): void
    {
        $driver = (string) $this->settings->get('mail_driver', 'smtp');

        // Fall back to logging rather than throwing if the site owner picked a
        // provider whose package is not installed on their host.
        if (! $this->driverIsAvailable($driver)) {
            $driver = 'log';
        }

        config([
            'mail.default' => $driver,
            'mail.from.address' => $this->settings->get('mail_from_address') ?: config('mail.from.address'),
            'mail.from.name' => $this->settings->get('mail_from_name') ?: config('app.name'),
        ]);

        if ($driver === 'smtp') {
            $this->applySmtp();
        }
    }

    private function applySmtp(): void
    {
        $encryption = (string) $this->settings->get('mail_encryption', 'tls');

        config([
            'mail.mailers.smtp.host' => $this->settings->get('mail_host') ?: '127.0.0.1',
            'mail.mailers.smtp.port' => (int) $this->settings->get('mail_port', 587),
            'mail.mailers.smtp.username' => $this->settings->get('mail_username') ?: null,
            'mail.mailers.smtp.password' => $this->settings->get('mail_password') ?: null,
            // Laravel 11+ reads 'scheme'; 'smtps' implies an implicit TLS port.
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => in_array($encryption, ['tls', 'ssl'], true) ? $encryption : null,
        ]);
    }

    /**
     * Resend and Postmark ship as optional Symfony transports; SES needs the
     * AWS SDK. Report honestly rather than failing at send time.
     */
    public function driverIsAvailable(string $driver): bool
    {
        return match ($driver) {
            'resend' => class_exists(\Resend\Laravel\ResendServiceProvider::class)
                || class_exists(\Symfony\Component\Mailer\Bridge\Resend\Transport\ResendApiTransport::class),
            'ses' => class_exists(\Aws\Ses\SesClient::class) || class_exists(\Aws\SesV2\SesV2Client::class),
            'postmark' => class_exists(\Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport::class),
            default => true,
        };
    }

    /** What a site owner has to install to use a given provider. */
    public function installHintFor(string $driver): ?string
    {
        return match ($driver) {
            'resend' => 'composer require resend/resend-laravel',
            'ses' => 'composer require aws/aws-sdk-php',
            'postmark' => 'composer require symfony/postmark-mailer',
            default => null,
        };
    }

    /** Which .env keys a given provider expects. */
    public function envKeysFor(string $driver): array
    {
        return match ($driver) {
            'resend' => ['RESEND_KEY'],
            'ses' => ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION'],
            'postmark' => ['POSTMARK_TOKEN'],
            default => [],
        };
    }

    /**
     * Reports which of the required .env keys are actually populated, so the
     * admin screen can show a green/red state per provider.
     */
    public function envStatusFor(string $driver): array
    {
        $status = [];

        foreach ($this->envKeysFor($driver) as $key) {
            $status[$key] = filled(env($key));
        }

        return $status;
    }
}
