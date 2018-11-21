<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Provider
 * @package ThemeHouse\InstallAndUpgrade\Entity
 *
 * @property integer provider_id
 * @property string provider_class
 * @property string addon_id
 *
 * @property string title
 * @property AbstractHandler handler
 */
class Provider extends Entity
{
    /**
     * @return string
     */
    public function getTitle()
    {
        $handler = $this->handler;
        return $handler ? $handler->getTitle($this) : '';
    }

    /**
     * @return AbstractHandler|null
     * @throws \Exception
     */
    public function getHandler()
    {
        $class = \XF::stringToClass($this->provider_class, '%s\InstallAndUpgrade\%s');
        if (!class_exists($class)) {
            return null;
        }

        $class = \XF::extendClass($class);
        return new $class($this->provider_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_provider';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:InstallUpgadeProvider';
        $structure->primaryKey = 'provider_id';
        $structure->columns = [
            'provider_id' => ['type' => self::STR, 'maxLength' => 25, 'match' => 'alphanumeric', 'required' => true],
            'provider_class' => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
            'addon_id' => ['type' => self::BINARY, 'maxLength' => 50, 'default' => ''],
            'is_unique' => ['type' => self::BOOL, 'default' => 0]
        ];
        $structure->getters = [
            'title' => false,
            'handler' => true
        ];
        $structure->relations = [];

        return $structure;
    }
}