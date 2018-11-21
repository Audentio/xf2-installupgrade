<?php

namespace ThemeHouse\InstallAndUpgrade\Listener;

use ThemeHouse\Core\AutoLoad;

class App
{
    public static function setup(\XF\App $app)
    {
        AutoLoad::autoloadComposerPackages('ThemeHouse/InstallAndUpgrade');
    }
}