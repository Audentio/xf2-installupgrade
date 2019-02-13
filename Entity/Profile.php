<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null profile_id
 * @property string provider_id
 * @property string page_title
 * @property bool has_tfa
 * @property string base_url
 * @property array options
 * @property bool active
 * @property bool requires_decryption
 * @property array last_error_messages
 */
class Profile extends Entity
{
    protected $secret;
    protected $credentials;

//    /**
//     * @return mixed
//     */
//    public function getProductsFromProvider()
//    {
//        $provider = $this->Provider;
//
//        if (!$provider) {
//            return null;
//        }
//
//        $handler = $provider->handler;
//
//        if (!$handler) {
//            return null;
//        }
//
//        if ($this->secret) {
//            $handler->setEncryptionSecret($this->secret);
//        }
//        return $handler->getProductsFromProvider($this);
//    }
//
//    protected function _postDelete()
//    {
//        \XF::app()->db()->delete('xf_th_installupgrade_product', 'profile_id = ?', [$this->profile_id]);
//        parent::_postDelete();
//    }

    /**
     * @return null|\ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler
     * @throws \Exception
     */
    public function getHandler()
    {
        $repo = $this->getProfileRepository();
        $handler = $repo->getHandler($this->provider_id);
        $handler->setProfile($this);
        return $handler;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getCredentials()
    {
        if (!$this->credentials) {
            $handler = $this->getHandler();
            $handler->setEncryptionSecret($this->secret);
            $this->credentials = $handler->decryptCredentials($this->options);
        }

        return $this->credentials;
    }

    public function setEncryptionSecret($secret)
    {
        $this->secret = $secret;
    }

    protected function _postSave()
    {
        if (($this->isInsert() || $this->isChanged('options')) && $this->active) {
            $handler = $this->getHandler();
    
            if ($handler->getCapability('productList')) {
                $this->app()
                    ->jobManager()
                    ->enqueue('ThemeHouse\InstallAndUpgrade:GetProducts', [], true)
                ;
            }
        }
    }
    
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_profile';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:Profile';
        $structure->primaryKey = 'profile_id';
        $structure->columns = [
            'profile_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'provider_id' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
            'page_title' => [
                'type' => self::STR,
                'maxLength' => 100,
                'required' => 'please_enter_valid_title'
            ],
            'has_tfa' => ['type' => self::BOOL, 'default' => 0],
            'base_url' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'options' => ['type' => self::JSON_ARRAY, 'default' => []],
            'active' => ['type' => self::BOOL, 'default' => 1],
            'requires_decryption' => ['type' => self::BOOL, 'default' => 0],
            'last_error_messages' => ['type' => self::JSON_ARRAY, 'default' => []],
        ];
        $structure->getters = [];

        return $structure;
    }

    /**
     * @return \ThemeHouse\InstallAndUpgrade\Repository\Profile
     */
    protected function getProfileRepository()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\InstallAndUpgrade:Profile');
    }
}