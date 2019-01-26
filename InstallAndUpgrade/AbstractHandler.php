<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\LanguageHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\MultiProfile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\ProductList;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\TFA;
use ThemeHouse\InstallAndUpgrade\Repository\Log;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

abstract class AbstractHandler implements \ArrayAccess
{
    protected $values;

    /**
     * @var \XF\Mvc\Entity\Manager
     */
    protected $em;

    /**
     * @var Profile
     */
    protected $profile;

    /**
     * @var \XF\App
     */
    protected $app;

    public function __construct(Profile $profile = null)
    {
        $this->app = \XF::app();
        $this->em = $this->app->em();
        $this->profile = $profile;
    }

    /**
     * @param Profile $profile
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
    }

    protected function repository($shortName)
    {
        return $this->em->getRepository($shortName);
    }

    protected function finder($shortName)
    {
        return $this->em->getFinder($shortName);
    }

    public function service($class)
    {
        return call_user_func_array([$this->app, 'service'], func_get_args());
    }

    protected function app()
    {
        return $this->app;
    }

    protected function em()
    {
        return $this->em;
    }

    protected function db()
    {
        return $this->db();
    }
    
    /**
     * @param Product $product
     * @param $action
     * @param array $extra
     *
     * @throws \XF\PrintableException
     */
    protected function log(Product $product, $action, $extra = []) {
        /** @var Log $repo */
        $repo = \XF::repository('ThemeHouse\InstallAndUpgrade:Log');
        $repo->log($product, $action, $extra);
    }

    public function view($viewClass = '', $templateName = '', array $params = [])
    {
        return new View($viewClass, $templateName, $params);
    }

    public function redirect($url, $message = null, $type = 'temporary')
    {
        if ($message === null) {
            $message = \XF::phrase('your_changes_have_been_saved');
        }
        return new Redirect($url, $type, $message);
    }

    public function error($error, $code = 200)
    {
        return new Error($error, $code);
    }


    /**
     * @param string $link
     * @param mixed $data
     * @param array $parameters
     *
     * @return string
     */
    public function buildLink($link, $data = null, array $parameters = [])
    {
        return $this->app->router()->buildLink($link, $data, $parameters);
    }

    public function offsetExists($key)
    {
        return $this->__isset($key);
    }

    public function offsetGet($key)
    {
        return $this->get($key);
    }

    public function offsetSet($key, $value)
    {
        $this->set($key, $value);
    }

    public function offsetUnset($offset)
    {
        throw new \LogicException('Handler offsets may not be unset');
    }

    protected function convertKey($key)
    {
        $key = explode('_', $key);
        $key = array_map("ucfirst", $key);
        $key = join('', $key);
        return $key;
    }

    protected function set($key, $value)
    {
        $this->values[$this->convertKey($key)] = $value;
    }

    protected function get($key)
    {
        $key = $this->convertKey($key);
        if (method_exists($this, "get{$key}")) {
            return $this->{"get{$key}"}();
        }

        if (isset($this->values[$key])) {
            return $this->values[$key];
        }

        return null;
    }

    public function __isset($key)
    {
        $key = $this->convertKey($key);

        if (method_exists($this, "get{$key}")) {
            return true;
        }

        return isset($this->values[$key]);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * @param $message
     * @throws Exception
     */
    protected function exception($message)
    {
        throw new Exception(new Error($message));
    }
    
    /**
     * @param string $type Either "style", "language" or "addOn"
     * @param string $error The error message itself
     * @param bool $overwrite If true, this will overwrite all previous error messages for this type
     */
    protected function logProfileError($type, $error, $overwrite = true)
    {
        if (!$this->profile)
        {
            return;
        }
        
        $existingErrors = $this->profile->last_error_messages;
        if (empty($existingErrors[$type]) || $overwrite)
        {
            $existingErrors[$type] = [];
        }
        
        $existingErrors[$type][] = $error;
        $this->profile->fastUpdate('last_error_messages', $existingErrors);
    }

    public function getCapabilities()
    {
        return [
            'style' => $this instanceof StyleHandler,
            'language' => $this instanceof LanguageHandler,
            'addOn' => $this instanceof AddOnHandler,
            'tfa' => $this instanceof TFA,
            'productList' => $this instanceof ProductList,
            'encryptCredentials' => $this instanceof EncryptCredentials,
            'multiple' => $this instanceof MultiProfile
        ];
    }

    public function getCapability($capability)
    {
        $capabilities = $this->getCapabilities();
        return isset($capabilities[$capability]) ? $capabilities[$capability] : false;
    }

    abstract public function compareVersions($currentVersion, $latestVersion, $operator = '<');

    abstract public function getTitle();

    abstract public function getProfileOptionsTemplate();

    abstract public function getProfileDefaultOptions();

    abstract public function verifyOptions(array $options);
}