<?php

namespace ThemeHouse\InstallAndUpgrade\Listener;

use XF\AddOn\AddOn;
use XF\Entity\AddOn as AddOnEntity;

class AddOnPostUpgrade
{
    /**
     * @param AddOn $addOn
     * @param AddOnEntity $installedAddOn
     * @param array $json
     * @param array $stateChanges
     * @throws \XF\PrintableException
     */
    public static function run(AddOn $addOn, AddOnEntity $installedAddOn, array $json, array &$stateChanges)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $installedAddOn */
        $installedAddOn->importInstallUpgradeJSON();
    }
}