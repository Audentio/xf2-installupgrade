<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class AddOn
 * @package ThemeHouse\InstallAndUpgrade\Entity
 *
 * @property string addon_id
 * @property string download_url
 * @property boolean auto_update
 * @property boolean update_check
 * @property boolean update_available
 * @property string latest_version
 * @property string alert_sent
 * @property array extra
 * @property string json_hash
 *
 * @property AbstractHandler handler
 * @property \XF\Entity\AddOn AddOn
 */
class AddOn extends Entity
{
    protected function _preSave()
    {
        if ($this->update_check && $this->isChanged('update_available') && $this->update_available) {
            /** @var InstallAndUpgrade $repo */
            $repo = $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
            $repo->sendAdminAlerts('addOn', 'updateAvailable', [
                'addonTitle' => $this->AddOn->title,
                'version' => $this->latest_version
            ]);
        }

        parent::_preSave();
    }

    /**
     * @return AbstractHandler|null
     */
    public function getHandler()
    {
        return $this->repository('ThemeHouse\InstallAndUpgrade:Handler')->getHandler('addOn',
            $this->addon_id, $this);
    }

    /**
     * @return Profile|null
     */
    public function getProfile()
    {
        if (!$this->handler) {
            return null;
        }

        return $this->handler->getProfile('addOn', $this->addon_id, $this);
    }

    /**
     * @return string
     */
    public function getAddonIdUrl()
    {
        // casing matches the addon_id_url getter
        return $this->repository('XF:AddOn')->convertAddOnIdToUrlVersion($this->addon_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_addon';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:AddOn';
        $structure->primaryKey = 'addon_id';
        $structure->columns = [
            'addon_id' => [
                'type' => self::BINARY,
                'maxLength' => 50,
                'required' => 'please_enter_valid_addon_id',
                'unique' => 'add_on_ids_must_be_unique',
                'match' => [
                    '#^[a-z][a-z0-9]*(/[a-z][a-z0-9]*)?$#i',
                    'please_enter_valid_add_on_id_using_rules'
                ]
            ],
            'latest_version' => ['type' => self::STR, 'default' => ''],
            'download_url' => ['type' => self::STR, 'default' => ''],
            'auto_update' => ['type' => self::BOOL, 'default' => 0],
            'update_check' => ['type' => self::BOOL, 'default' => 1],
            'update_available' => ['type' => self::BOOL, 'default' => 0],
            'extra' => ['type' => self::JSON, 'default' => []],
            'json_hash' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
        ];

        $structure->getters = [
            'handler' => true,
            'addon_id_url' => true,
        ];

        $structure->relations = [
            'AddOn' => [
                'entity' => 'XF:AddOn',
                'type' => self::TO_ONE,
                'conditions' => 'addon_id',
                'primary' => true
            ],
        ];

        return $structure;
    }
}