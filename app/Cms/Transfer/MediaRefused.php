<?php

namespace App\Cms\Transfer;

/**
 * One file could not be brought in. Always caught and counted: an import that
 * gave up because a single 2014 header image 404s would be useless.
 */
class MediaRefused extends \RuntimeException {}
