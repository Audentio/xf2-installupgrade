<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use ThemeHouse\InstallAndUpgrade\Entity\Style;

class CheckStylesForUpdates
{
    public static function run()
    {
        $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:Style');
        $finder->where('update_check', '=', 1);
        $styles = $finder->fetch();

        foreach ($styles as $style) {
            /** @var Style $style */
            $handler = $style->handler;

            if (!$handler) {
                continue;
            }

            $handler->checkStyleForUpdates($style);
        }
    }
}