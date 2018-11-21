<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Entity\Phrase;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * Class Log
 * @package ThemeHouse\InstallAndUpgrade\Entity
 *
 * @property integer log_id
 * @property string content_type
 * @property string content_id
 * @property integer user_id
 * @property integer timestamp
 * @property string action
 * @property string from_version
 * @property string to_version
 *
 * @property Language|Style|AddOn Content
 * @property Language Language
 * @property Style Style
 * @property AddOn AddOn
 * @property User User
 * @property Phrase content_type_phrase
 * @property string content_type_url
 */
class Log extends Entity
{
    /**
     * @return null|AddOn|Language|Style
     */
    public function getContent()
    {
        switch ($this->content_type) {
            case 'language':
                return $this->Language;

            case 'style':
                return $this->Style;

            case 'addOn':
                return $this->AddOn;

            default:
                return null;
        }
    }

    /**
     * @return mixed|null|string
     */
    public function getContentTypeUrl()
    {
        switch ($this->content_type) {
            case 'language':
                return \XF::app()->router('admin')->buildLink('languages');

            case 'style':
                return \XF::app()->router('admin')->buildLink('styles');

            case 'addOn':
                return \XF::app()->router('admin')->buildLink('add-ons');

            default:
                return null;
        }
    }

    /**
     * @return \XF\Phrase
     */
    public function getContentTypePhrase()
    {
        switch ($this->content_type) {
            case 'addOn':
                return \XF::phrase('add_on');

            default:
                return \XF::phrase($this->content_type);
        }
    }

    /**
     * @return \XF\Phrase
     */
    public function getActionPhrase()
    {
        return \XF::phrase("th_iau_action_{$this->action}");
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_log';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Log';
        $structure->primaryKey = 'log_id';
        $structure->columns = [
            'log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'content_type' => [
                'type' => self::STR,
                'maxLength' => 25,
                'required' => true,
                'allowedValues' => ['addOn', 'language', 'style']
            ],
            'action' => [
                'type' => self::STR,
                'default' => 'upgrade',
                'allowedValues' => ['install', 'upgrade']
            ],
            'from_version' => ['type' => self::STR],
            'to_version' => ['type' => self::STR],
            'content_id' => ['type' => self::STR, 'required' => true],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'timestamp' => ['type' => self::UINT, 'default' => \XF::$time]
        ];

        $structure->getters = [
            'Content' => true,
            'content_type_phrase' => true,
            'content_type_url' => true,
            'action_phrase' => true,
        ];

        $structure->defaultWith = ['User'];

        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'AddOn' => [
                'entity' => 'XF:AddOn',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['addon_id', '=', '$content_id']
                ]
            ],
            'Style' => [
                'entity' => 'XF:Style',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['style_id', '=', '$content_id']
                ]
            ],
            'Language' => [
                'entity' => 'XF:Language',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['language_id', '=', '$content_id']
                ]
            ]
        ];

        return $structure;
    }
}