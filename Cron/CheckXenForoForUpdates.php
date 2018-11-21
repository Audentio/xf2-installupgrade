<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\XenForo;

class CheckXenForoForUpdates
{
    /**
     * @throws \Exception
     */
    public static function run()
    {
        $class = 'ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\XenForo';
        $class = \XF::app()->extendClass($class);
        /** @var XenForo $handler */
        $handler = new $class('XenForo');

        $handler->checkForUpdates();
    }
}