<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Style
 * @package ThemeHouse\InstallAndUpgrade\Entity
 *
 * @property integer style_id
 * @property string download_url
 * @property boolean auto_update
 * @property boolean update_check
 * @property boolean update_available
 * @property string current_version
 * @property string latest_version
 * @property array extra
 * @property string json_hash
 *
 * @property AbstractHandler handler
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