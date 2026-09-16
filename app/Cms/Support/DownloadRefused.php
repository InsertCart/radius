<?php

namespace App\Cms\Support;

use RuntimeException;

/**
 * Raised by VerifiedDownload when an archive is refused.
 *
 * It carries a machine-readable reason rather than only a sentence, because
 * the same check is worded differently depending on what is being downloaded:
 * "this release" for a core update, "this theme" in the marketplace. Callers
 * translate the reason into their own message, so the wording site owners
 * already know is never changed by sharing the checks.
 */
class DownloadRefused extends RuntimeException
{
    public const MISSING_URL = 'missing_url';
    public const INVALID_URL = 'invalid_url';
    public const INSECURE_URL = 'insecure_url';
    public const BLOCKED_HOST = 'blocked_host';
    public const MISSING_CHECKSUM = 'missing_checksum';
    public const WORKSPACE = 'workspace';
    public const UNREACHABLE = 'unreachable';
    public const HTTP_ERROR = 'http_error';
    public const EMPTY_FILE = 'empty_file';
    public const TOO_LARGE = 'too_large';
    public const CHECKSUM_MISMATCH = 'checksum_mismatch';

    /**
     * @param  array<string, mixed>  $context  details a message may need: 'status', 'bytes', 'host'
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $context = [],
    ) {
        parent::__construct($reason);
    }
}
