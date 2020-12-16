<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Api\Result\EntityResult;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int profile_id
 * @property string product_id
 * @property string product_type
 * @property string content_id
 * @property string title
 * @property string description
 * @property string current_version
 * @property string latest_version
 * @property bool update_available
 * @property array|null extra
 * @property bool installed
 * @property string json_hash
 *
 * GETTERS
 * @property mixed content_type
 * @property mixed Content
 *
 * RELATIONS
 * @property Profile Profile
 */
class Product extends Entity
{
    /**
     * @param EntityResult $result
     * @param int $verbosity
     * @param array $options
     */
    protected function setupApiResultData(
        EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
    )
    {
        $result->includeColumn([
            'profile_id',
            'product_id',
            'product_type',
            'content_id',
            'title',
            'description',
            'current_version',
            'latest_version',
            'update_available',
            'extra',
            'installed',
            'json_hash',
        ]);

        if (!empty($options['withContent']))
        {
            /** @noinspection PhpUndefinedFieldInspection */
            $result->Content = $this->Content;
        }
    }

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_product';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Product';
        $structure->primaryKey = ['profile_id', 'product_id'];
        $structure->columns = [
            'profile_id' => ['type' => self::UINT, 'required' => true],
            'product_id' => ['type' => self::STR, 'required' => true, 'maxLength' => 60],
            'product_type' => ['type' => self::STR, 'allowedValues' => ['addOn', 'style', 'language']],
            'content_id' => ['type' => self::STR, 'maxLength' => 60, 'default' => ''],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'description' => ['type' => self::STR, 'default' => ''],
            'current_version' => ['type' => self::STR, 'default' => ''],
            'latest_version' => ['type' => self::STR, 'required' => true],
            'update_available' => ['type' => self::BOOL, 'default' => 0],
            'extra' => ['type' => self::JSON, 'default' => []],
            'installed' => ['type' => self::BOOL, 'default' => false],
            'json_hash' => ['type' => self::STR, 'maxLength' => 64, 'default' => ''],
        ];

        $structure->getters = [
            'content_type' => true,
            'Content' => true
        ];

        $structure->relations = [
            'Profile' => [
                'entity' => 'ThemeHouse\InstallAndUpgrade:Profile',
                'type' => self::TO_ONE,
                'conditions' => 'profile_id',
                'primary' => true
            ]
        ];

        $structure->defaultWith = 'Profile';

        return $structure;
    }

    /**
     * @return null|string
     */
    public function getContentType()
    {
        switch ($this->product_type) {
            case 'addOn':
                return 'XF:AddOn';

            case 'language':
                return 'XF:Language';

            case 'style':
                return 'XF:Style';

            default:
                return null;
        }
    }

    /**
     * @return null|Entity
     */
    public function getContent()
    {
        return $this->em()->find($this->content_type, $this->content_id);
    }
}