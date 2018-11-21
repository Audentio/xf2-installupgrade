<?php

namespace ThemeHouse\InstallAndUpgrade\Listener;

use XF\AddOn\AddOn;
use XF\Entity\AddOn as AddOnEntity;

class AddOnPreUninstall
{
    /**
     * @param AddOn $addOn
     * @param AddOnEntity $installedAddOn
     * @param array $json
     * @throws \XF\PrintableException
     */
    public static function run(AddOn $addOn, AddOnEntity $installedAddOn, array $json)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $installedAddOn */
        $data = $installedAddOn->THInstallUpgradeData;
        if ($data) {
            $data->delete();
        }
    }
}