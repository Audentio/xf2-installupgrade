<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

class UpdateCheck
{
    public static function run()
    {
        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:UpdateCheck', [], true);
    }
}