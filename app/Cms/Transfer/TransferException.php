<?php

namespace App\Cms\Transfer;

/**
 * Thrown when a bundle cannot be read at all: the wrong kind of file, a
 * manifest from a newer Radius, an archive that will not open.
 *
 * A record that fails on its own is not this - that is counted on the report
 * and the import carries on, because one broken post should not cost somebody
 * the other nine hundred.
 */
class TransferException extends \RuntimeException {}
