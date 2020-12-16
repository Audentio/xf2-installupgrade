<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use ThemeHouse\InstallAndUpgrade\Entity\ApiTrait;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\Entity\Structure;

/**
 * Class Style
 * @package ThemeHouse\InstallAndUpgrade\XF\Entity
 *
 * @property integer th_iau_profile_id
 * @property string th_iau_product_id
 * @property string th_iau_xml
 * @property string th_iau_current_version
 *
 * @property Product THIAUProduct
 */
class Style extends XFCP_Style
{
    use ApiTrait;

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['th_iau_profile_id'] = [
            'type' => self::UINT,
            'default' => 0
        ];

        $structure->columns['th_iau_product_id'] = [
            'type' => self::STR,
            'default' => 0
        ];

        $structure->columns['th_iau_xml'] = [
            'type' => self::STR,
            'default' => 0
        ];

        $structure->columns['th_iau_current_version'] = [
            'type' => self::STR,
            'default' => 0
        ];

        $structure->relations['THIAUProduct'] = [
            'entity' => 'ThemeHouse\InstallAndUpgrade:Product',
            'type' => self::TO_ONE,
            'conditions' => [
                ['product_type', '=', 'style'],
                ['product_id', '=', '$th_iau_product_id'],
                ['profile_id', '=', '$th_iau_profile_id']
            ]
        ];

        return $structure;
    }
}