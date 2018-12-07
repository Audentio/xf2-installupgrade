<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int profile_id
 * @property string product_id
 * @property string product_type
 * @property string title
 * @property string description
 * @property string version
 * @property array|null extra
 *
 * RELATIONS
 * @property \ThemeHouse\InstallAndUpgrade\Entity\Profile Profile
 */
class Product extends Entity
{
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_product';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Product';
        $structure->primaryKey = ['profile_id', 'product_id'];
        $structure->columns = [
            'profile_id' => ['type' => self::UINT, 'required' => true],
            'product_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 60],
            'product_type' => ['type' => self::STR, 'allowedValues' => ['addOn', 'style', 'language']],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'description' => ['type' => self::STR, 'default' => ''],
            'version' => ['type' => self::STR, 'required' => true],
            'extra' => ['type' => self::JSON, 'default' => []]
        ];

        $structure->getters = [];
        $structure->relations = [
            'Profile' => [
                'entity' => 'ThemeHouse\InstallAndUpgrade:Profile',
                'type' => self::TO_ONE,
                'conditions' => 'profile_id',
                'primary' => true
            ]
        ];
        $structure->defaultWith = 'Profile';

        return $structure;
    }
}