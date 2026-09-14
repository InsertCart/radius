<?php

namespace App\Cms\Themes;

use RuntimeException;

/**
 * Raised when an uploaded theme fails validation. The message is written for
 * the site owner, so it is safe to show directly in the admin panel.
 */
class ThemeInstallException extends RuntimeException
{
}
