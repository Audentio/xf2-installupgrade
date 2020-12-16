<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use XF;

/**
 * Class GetProducts
 * @package ThemeHouse\InstallAndUpgrade\Cron
 */
class GetProducts
{
    /**
     *
     */
    public static function run()
    {
        XF::app()
            ->jobManager()
            ->enqueue('ThemeHouse\InstallAndUpgrade:GetProducts');
    }
}