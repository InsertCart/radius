<?php

namespace App\Cms\Plugins;

/**
 * A plugin could not be installed, loaded or changed. The message is written
 * for site owners and is safe to show in the admin panel.
 */
class PluginInstallException extends \RuntimeException {}
