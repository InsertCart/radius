<?php

namespace App\Cms\Marketplace;

use RuntimeException;

/**
 * Raised when the marketplace cannot do what was asked. The message is written
 * for the site owner, so it is safe to show directly in the admin panel.
 *
 * Anything that is not one of these is an unexpected fault: it gets reported
 * and the admin sees a generic message, because the detail could name paths.
 */
class MarketplaceException extends RuntimeException
{
}
