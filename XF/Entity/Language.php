<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use ThemeHouse\InstallAndUpgrade\Entity\ApiTrait;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\Entity\Structure;

/**
 * Class Language
 * @package ThemeHouse\InstallAndUpgrade\XF\Entity
 *
 * @property Product THIAUProduct
 */
class Language extends XFCP_Language
{
    use ApiTrait;

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->relations['THIAUProduct'] = [
            'entity' => 'ThemeHouse\InstallAndUpgrade:Product',
            'type' => self::TO_ONE,
            'conditions' => [
                ['product_type', '=', 'language'],
                ['content_id', '=', '$language_id'],
            ]
        ];

        return $structure;
    }
}
