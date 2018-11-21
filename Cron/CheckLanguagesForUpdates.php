<?php

namespace ThemeHouse\InstallAndUpgrade\Cron;

use ThemeHouse\InstallAndUpgrade\Entity\Language;

class CheckLanguagesForUpdates
{
    public static function run()
    {
        $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:Language');
        $finder->where('update_check', '=', 1);
        $languages = $finder->fetch();

        foreach ($languages as $language) {
            /** @var Language $language */
            $handler = $language->handler;

            if (!$handler) {
                continue;
            }

            $handler->checkLanguageForUpdates($language);
        }
    }
}