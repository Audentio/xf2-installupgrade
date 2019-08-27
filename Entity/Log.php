<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int log_id
 * @property int profile_id
 * @property string product_id
 * @property int user_id
 * @property int log_date
 * @property string action
 * @property string content_type
 * @property string content_id
 * @property array|null extra
 *
 * GETTERS
 * @property mixed Content
 * @property mixed action_phrase
 *
 * RELATIONS
 * @property \XF\Entity\User User
 * @property \ThemeHouse\InstallAndUpgrade\Entity\Profile Profile
 * @property \ThemeHouse\InstallAndUpgrade\Entity\Product Product
 */
class Log extends Entity
{
    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_log';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Log';
        $structure->primaryKey = 'log_id';

        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'profile_id' => ['type' => self::UINT, 'required' => true],
            'product_id' => ['type' => self::STR, 'maxLength' => 200, 'default' => ''],
            'user_id' => ['type' => self::UINT, 'default' => \XF::visitor()->user_id],
            'log_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'action' => ['type' => self::STR, 'required' => true],
            'content_type' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'content_id' => ['type' => self::STR, 'maxLength' => 200, 'default' => ''],
            'extra' => ['type' => self::JSON, 'default' => []]
        ];

        $structure->getters = [
            'Content' => true,
            'action_phrase' => true
        ];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Profile' => [
                'entity' => 'ThemeHouse\InstallAndUpgrade:Profile',
                'type' => self::TO_ONE,
                'conditions' => 'profile_id',
                'primary' => true
            ],
            'Product' => [
                'entity' => 'ThemeHouse\InstallAndUpgrade:Product',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['product_id', '=', '$product_id'],
                    ['profile_id', '=', '$profile_id']
                ]
            ]
        ];

        return $structure;
    }

    /**
     * @return null|Entity
     */
    public function getContent()
    {
        $types = [
            'addOn' => 'XF:AddOn',
            'style' => 'XF:Style',
            'language' => 'XF:Language'
        ];

        if ($this->content_type && $this->content_id) {
            return $this->em()->find($types[$this->content_type], $this->content_id);
        }

        return null;
    }

    /**
     * @return \XF\Phrase
     */
    public function getActionPhrase()
    {
        $args = array_merge(
            $this->Product->toArray(),
            $this->extra,
            $this->Content ? $this->Content->toArray() : []
        );
        return \XF::phrase('th_iau_log_action_' . $this->content_type . '_' . $this->action, $args);
    }
}