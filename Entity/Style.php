<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int style_id
 * @property string download_url
 * @property bool auto_update
 * @property bool update_check
 * @property bool update_available
 * @property string current_version
 * @property string latest_version
 * @property array|null extra
 * @property string json_hash
 *
 * GETTERS
 * @property AbstractHandler|null handler
 *
 * RELATIONS
 * @property \XF\Entity\Style Style
 */
class Style extends Entity
{
    protected function _preSave()
    {
        if ($this->update_check && $this->isChanged('update_available') && $this->update_available) {
            /** @var InstallAndUpgrade $repo */
            $repo = $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
            $repo->sendAdminAlerts('style', 'updateAvailable', [
                'styleTitle' => $this->Style->title,
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
        return $this->repository('ThemeHouse\InstallAndUpgrade:Handler')->getHandler('style',
            $this->style_id, $this);
    }

    /**
     * @return Profile|null
     */
    public function getProfile()
    {
        if (!$this->handler) {
            return null;
        }

        $this->handler->getProfile('style', $this->style_id, $this);
    }

    public function updateVersion()
    {
        $this->fastUpdate('current_version', $this->latest_version);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_style';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Style';
        $structure->primaryKey = 'style_id';
        $structure->columns = [
            'style_id' => ['type' => self::UINT, 'required' => true],
            'download_url' => ['type' => self::STR, 'default' => ''],
            'auto_update' => ['type' => self::BOOL, 'default' => 0],
            'update_check' => ['type' => self::BOOL, 'default' => 1],
            'update_available' => ['type' => self::BOOL, 'default' => 0],
            'current_version' => ['type' => self::STR, 'default' => ''],
            'latest_version' => ['type' => self::STR, 'default' => ''],
            'extra' => ['type' => self::JSON, 'default' => []],
            'json_hash' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
        ];

        $structure->getters = [
            'handler' => true
        ];

        $structure->relations = [
            'Style' => [
                'entity' => 'XF:Style',
                'type' => self::TO_ONE,
                'conditions' => 'style_id',
                'primary' => true
            ],
        ];

        return $structure;
    }
}