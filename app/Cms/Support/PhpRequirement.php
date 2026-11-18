<?php

namespace App\Cms\Support;

/**
 * The PHP versions this release needs, and the next one it is going to need.
 *
 * Kept in one place because a wrong answer here takes sites down. The updater
 * trusts the minimum published with a release: if it says a server is fine when
 * the new vendor/ will refuse to load there, the site stops booting, and with
 * it the admin panel that could have rolled the update back.
 *
 * public/index.php loads this file directly, before Composer, so it must stay
 * free of dependencies - no framework calls, no helpers.
 *
 * When the minimum rises, change MINIMUM here together with "php" and
 * "config.platform.php" in composer.json and php-version in release.yml.
 * php artisan cms:release and the test suite both refuse a mismatch.
 */
final class PhpRequirement
{
    /** The oldest PHP this release runs on. */
    public const MINIMUM = '8.2.0';

    /** The oldest PHP a coming release will run on. Site owners are warned early. */
    public const UPCOMING = '8.3.0';

    /** "8.2.0" as people say it: "8.2". */
    public static function short(string $version): string
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    public static function meetsMinimum(string $php = PHP_VERSION): bool
    {
        return version_compare($php, self::MINIMUM, '>=');
    }

    public static function meetsUpcoming(string $php = PHP_VERSION): bool
    {
        return version_compare($php, self::UPCOMING, '>=');
    }

    /** What to tell a site owner whose server will fall behind, if anything. */
    public static function upcomingNotice(string $php = PHP_VERSION): ?string
    {
        if (self::meetsUpcoming($php)) {
            return null;
        }

        return 'This server runs PHP '.$php.'. Your site keeps working as it is, but a future update will need PHP '
            .self::short(self::UPCOMING).' or newer and cannot be installed until the server has moved. '
            .'You can usually change the PHP version in your hosting control panel.';
    }
}
