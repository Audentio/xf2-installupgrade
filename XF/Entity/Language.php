<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use XF\Mvc\Entity\Structure;

class Language extends XFCP_Language
{
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
