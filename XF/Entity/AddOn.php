<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\Entity\Structure;

/**
 * Class AddOn
 * @package ThemeHouse\InstallAndUpgrade\XF\Entity
 *
 * @property Product THIAUProduct
 */
class AddOn extends XFCP_AddOn
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
                ['product_type', '=', 'addOn'],
                ['content_id', '=', '$addon_id']
            ],
            'defaultWith' => ['Profile']
        ];

        $structure->defaultWith[] = 'THIAUProduct';

        return $structure;
    }

    /**
     *
     */
    protected function _postDelete()
    {
        $product = $this->THIAUProduct;
        if ($product) {
            $product->fastUpdate('installed', false);
        }

        parent::_postDelete();
    }

    /**
     *
     */
    protected function _postSave()
    {
        $product = $this->THIAUProduct;
        if ($product) {
            try {
                $profile = $product->Profile;
                if(!$profile) {
                    $product->delete();
                    return parent::_postSave();
                }

                $handler = $profile->getHandler();

                if ($handler) {
                    $updateAvailable = $handler->compareVersions($this->version_string,
                        $product->latest_version);
                } else {
                    $updateAvailable = false;
                }
            } catch (\Exception $e) {
                $updateAvailable = false;
            }

            $product->bulkSet([
                'installed' => true,
                'current_version' => $this->version_string,
                'update_available' => $updateAvailable,
                'json_hash' => $this->json_hash
            ]);

            $product->saveIfChanged();
        }

        parent::_postSave();
    }
}