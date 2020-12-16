<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use XF;

/**
 * Class UpdateCheck
 * @package ThemeHouse\InstallAndUpgrade\Cron
 */
class UpdateCheck
{
    /**
     *
     */
    public static function run()
    {
        XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:UpdateCheck');
    }
}