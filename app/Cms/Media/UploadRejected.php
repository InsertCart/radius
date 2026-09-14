<?php

namespace App\Cms\Media;

use RuntimeException;

/**
 * Raised when an upload fails a safety check. The message is written for the
 * person uploading, so it is safe to show them directly.
 */
class UploadRejected extends RuntimeException
{
}
