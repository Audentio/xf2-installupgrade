<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

class GetProducts
{
    public static function run()
    {
        \XF::app()
            ->jobManager()
            ->enqueue('ThemeHouse\InstallAndUpgrade:GetProducts')
        ;
    }
}