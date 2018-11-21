<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use ThemeHouse\InstallAndUpgrade\Entity\AddOn;

class CheckAddOnsForUpdates
{
    public static function run()
    {
        $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:AddOn');
        $finder->where('update_check', '=', 1);
        $addOns = $finder->fetch();

        foreach ($addOns as $addOn) {
            /** @var AddOn $addOn */
            $handler = $addOn->handler;

            if (!$handler) {
                continue;
            }

            $handler->checkAddOnForUpdates($addOn);
        }
    }
}