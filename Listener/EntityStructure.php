<?php

namespace ThemeHouse\InstallAndUpgrade\Listener;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Manager;
use XF\Mvc\Entity\Structure;

class EntityStructure
{
    public static function style(Manager $em, Structure &$structure)
    {
        $structure->relations['THInstallUpgradeData'] = [
            'entity' => 'ThemeHouse\InstallAndUpgrade:Style',
            'type' => Entity::TO_ONE,
            'conditions' => 'style_id',
            'primary' => true
        ];
        $structure->defaultWith[] = 'THInstallUpgradeData';
    }

    public static function addOn(Manager $em, Structure &$structure)
    {
        $structure->relations['THInstallUpgradeData'] = [
            'entity' => 'ThemeHouse\InstallAndUpgrade:AddOn',
            'type' => Entity::TO_ONE,
            'conditions' => 'addon_id',
            'primary' => true
        ];
        $structure->defaultWith[] = 'THInstallUpgradeData';
    }

    public static function language(Manager $em, Structure &$structure)
    {
        $structure->relations['THInstallUpgradeData'] = [
            'entity' => 'ThemeHouse\InstallAndUpgrade:Language',
            'type' => Entity::TO_ONE,
            'conditions' => 'language_id',
            'primary' => true
        ];
        $structure->defaultWith[] = 'THInstallUpgradeData';
    }
}