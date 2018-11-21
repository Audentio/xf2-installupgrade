<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Message\ResponseInterface;
use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\Entity\Provider;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use ThemeHouse\InstallAndUpgrade\Util\Encryption;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Util\File;

abstract class AbstractHandler
{
    /** @var CookieJar */
    protected $cookieJar;

    /** @var \GuzzleHttp\Client */
    protected $httpClient;

    /** @var string */
    protected $providerId;

    /** @var \XF\App */
    protected $app;

    /** @var \XF\Mvc\Entity\Manager */
    protected $em;

    protected $tfaKey;

    protected $secret;

    protected $credentials;
    
    public $supportsProductList = true;

    public function encryptCredentials(array $options)
    {
        $encryptedOptions = $this->getEncryptedOptions();
        if ($this->secret && $this->canEncryptCredentials()) {
            foreach ($options as $key=>&$value) {
                if (in_array($key, $encryptedOptions)) {
                    $value = Encryption::encryptString($value, $this->secret);
                    $options[$key.'_encrypted'] = true;
                }
            }
        }

        return $options;
    }

    public function decryptCredentials(array $options)
    {
        $encryptedOptions = $this->getEncryptedOptions();
        if ($this->secret && $this->canEncryptCredentials()) {
            foreach ($options as $key=>&$value) {
                if (in_array($key, $encryptedOptions) && isset($options[$key.'_encrypted'])) {
                    $value = Encryption::decryptString($value, $this->secret);
                    unset($options[$key.'_encrypted']);
                }
            }
        }

        return $options;
    }

    /**
     * @return bool
     */
    public function canEncryptCredentials()
    {
        return false;
    }

    /**
     * @return array
     */
    public function getEncryptedOptions()
    {
        return [];
    }

    /**
     * @param $shortName
     * @return \XF\Mvc\Entity\Finder
     */
    protected function finder($shortName)
    {
        return $this->em->getFinder($shortName);
    }

    /**
     * @param $shortName
     * @return \XF\Mvc\Entity\Repository
     */
    protected function repository($shortName)
    {
        return $this->em->getRepository($shortName);
    }

    /**
     * @return \XF\AddOn\Manager
     */
    protected function getAddOnManager()
    {
        return $this->app->addOnManager();
    }

    /**
     * @return \XF\AddOn\DataManager
     */
    protected function getAddOnDataManager()
    {
        return $this->app->addOnDataManager();
    }

    /**
     * @param $class
     * @return \XF\Service\AbstractService
     */
    protected function service($class)
    {
        return $this->app->service($class);
    }

    /**
     * AbstractHandler constructor.
     * @param $providerId
     */
    public function __construct($providerId)
    {
        $this->app = \XF::app();
        $this->em = \XF::em();
        $this->providerId = $providerId;
        $this->httpClient = \XF::app()->http()->client();
        $this->cookieJar = new CookieJar();
    }

    public function setTfaKey($key)
    {
        $this->tfaKey = $key;
    }

    public function setEncryptionSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @param $message
     * @throws Exception
     */
    protected function exception($message) {
        throw new Exception(new Error($message));
    }

    /**
     * @param Profile $profile
     * @return string
     */
    public function renderConfig(Profile $profile)
    {
        $data = [
            'profile' => $profile
        ];
        return \XF::app()->templater()->renderTemplate('admin:install_and_upgrade_' . $this->providerId, $data);
    }

    /**
     * @param Provider $provider
     * @return \XF\Phrase
     */
    public function getTitle(Provider $provider)
    {
        $titlePhrase = 'install_upgrade_provider.' . $provider->provider_id;
        return \XF::phrase($titlePhrase);
    }

    /**
     * @param $url
     * @param array $options
     * @param string $method
     * @param bool $cookies
     * @param bool $throwErrors
     * @return ResponseInterface
     * @throws \Exception
     */
    protected function httpRequest($url, $options = [], $method = 'get', $cookies = true, $throwErrors = true)
    {
        if ($cookies) {
            $options['cookies'] = $this->cookieJar;
        }

        /** @var ResponseInterface $response */
        $response = $this->httpClient->{$method}($url, $options);

        if ($throwErrors && $response->getStatusCode() != 200) {
            $this->exception(\XF::phrase('th_iau_response_error', [
                'statusCode' => $response->getStatusCode(),
                'reason' => $response->getReasonPhrase()
            ]));
        }

        return $response;
    }

    /**
     * @return \ZipArchive
     * @throws \Exception
     */
    protected function getZipArchive()
    {
        try {
            $zip = new \ZipArchive();
        } catch (\Exception $e) {
            $this->exception('ZipArchive extension missing. Please contact your host or sysadmin to resolve this issue.');
        }

        return $zip;
    }

    /**
     * @param $file
     * @return \ZipArchive
     * @throws \Exception
     */
    protected function openZip($file)
    {
        $zip = $this->getZipArchive();
        $zip->open($file);
        return $zip;
    }

    /**
     * @param $file
     * @return array
     * @throws \Exception
     */
    protected function extractZip($file)
    {

        $xmls = [];

        if (!File::abstractedPathExists($file)) {
            $this->exception('File not found.');
        }

        $tempFile = File::copyAbstractedPathToTempFile($file);
        $tempDir = File::getTempDir() . DIRECTORY_SEPARATOR . \XF::$time;
        $zip = $this->openZip($tempFile);

        $zip->extractTo($tempDir);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if (strpos($fileName, 'upload') === 0) {
                $realPath = \XF::getRootDirectory() . DIRECTORY_SEPARATOR . substr($fileName, 6);
                $tempPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

                if (file_exists($tempPath) && is_file($tempPath)) {
                    File::copyFile($tempDir . DIRECTORY_SEPARATOR . $fileName, $realPath);
                }
            } else {
                $realPath = $tempDir . DIRECTORY_SEPARATOR . $fileName;

                if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) == 'xml') {
                    $xmls[] = $realPath;
                }

                File::makeWritableByFtpUser($realPath);
            }
        }

        $zip->close();

        return [$tempDir, $xmls];
    }

    /**
     * @param $file
     * @param null $directory
     */
    protected function removeTempFiles($file, $directory = null)
    {
        if ($file) {
            File::deleteFromAbstractedPath($file);
        }

        if ($directory) {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param Product $product
     * @param $entity
     * @throws \XF\PrintableException
     */
    public function convertProductToData(Product $product, Entity $entity) {
        if($entity instanceof AddOn || $entity instanceof Style || $entity instanceof Language) {
            $data = $entity;
        }
        else {
            $data = $entity->getRelationOrDefault('THInstallUpgradeData');
        }

        $data->download_url = isset($product->extra['download_url']) ? $product->extra['download_url'] : '';
        $data->extra = $product->extra;

        if($data instanceof Style || $data instanceof Language) {
            $data->current_version = $product->version;
        }

        $data->save();
        $product->delete();
    }

    /**
     * @param string $action
     * @param null $fromVersion
     * @param string $toVersion
     * @param string $contentType
     * @param string $contentId
     * @throws \XF\PrintableException
     */
    protected function createLogEntry(
        $toVersion = '',
        $contentType = '',
        $contentId = '',
        $action = 'install',
        $fromVersion = null
    ) {
        $logEntry = $this->em->create('ThemeHouse\InstallAndUpgrade:Log');

        $logEntry->bulkSet([
            'from_version' => $fromVersion ?: '',
            'to_version' => $toVersion,
            'user_id' => \XF::visitor()->user_id,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'timestamp' => \XF::$time,
            'action' => $action
        ]);

        $logEntry->save();
    }

    protected function disableBoard()
    {
        if (\XF::app()->options()->th_iau_disableBoard) {
            $option = $this->em->find('XF:Option', 'boardActive');
            $option->fastUpdate('option_value', false);
        }
    }

    protected function enableBoard()
    {
        if (\XF::app()->options()->th_iau_disableBoard) {
            $option = $this->em->find('XF:Option', 'boardActive');
            $option->fastUpdate('option_value', true);
        }
    }

    /**
     * @param $product
     * @param $profile
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function installLanguage($product, $profile)
    {
        $this->disableBoard();

        /** @var Product $product */
        if (!($product instanceof Product)) {
            /** @var Product $product */
            $product = $this->em->find('ThemeHouse\InstallAndUpgrade:Product',
                [$profile->profile_id, $product]);
        }


        $abstractPath = $this->_downloadProduct($product);
        list($directory, $xmls) = $this->extractZip($abstractPath);

        foreach ($xmls as &$xml) {
            $xmlName = pathinfo($xml, PATHINFO_FILENAME);
            $abstractPath = sprintf('internal-data://install-upgrade/xmls/%s-%s/%s.xml',
                $profile->profile_id, $product->product_id, $xmlName);

            File::copyFileToAbstractedPath($xml, $abstractPath);
            $xml = [
                'path' => str_replace('internal-data://install-upgrade/xmls/', '', $abstractPath),
                'name' => $xmlName
            ];
        }

        $this->removeTempFiles(null, $directory);
        $this->enableBoard();

        return $xmls;
    }

    /**
     * @param $product
     * @param $profile
     * @return bool
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function installAddOn($product, $profile)
    {
        $this->disableBoard();


        /** @var Product $product */
        if (!($product instanceof Product)) {
            /** @var Product $product */
            $product = $this->em->find('ThemeHouse\InstallAndUpgrade:Product',
                [$profile->profile_id, $product]);
        }

        $abstractPath = $this->_downloadProduct($product);

        $tempFile = File::copyAbstractedPathToTempFile($abstractPath);

        $zip = $this->openZip($tempFile);

        $addonId = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if (strpos($fileName, 'addon.json') !== false) {
                preg_match('/src\/addons\/(.*)\/addon.json$/', $fileName, $matches);
                $addonId = $matches[1];
                break;
            }
        }

        $zip->close();

        if (!$addonId) {
            return null;
        }

        list($directory, $xmls) = $this->extractZip($abstractPath);
        $this->removeTempFiles(null, $directory);

        $this->createLogEntry($product->version, 'addOn', $addonId, 'install');
        $this->enableBoard();

        return $addonId;
    }

    /**
     * @param $product
     * @param $profile
     * @return mixed
     * @throws \Exception
     */
    public function installStyle($product, $profile)
    {
        $this->disableBoard();

        if (!($product instanceof Product)) {
            /** @var Product $product */
            $product = $this->em->find('ThemeHouse\InstallAndUpgrade:Product',
                [$profile->profile_id, $product]);
        }

        $abstractPath = $this->_downloadProduct($product);
        list($directory, $xmls) = $this->extractZip($abstractPath);

        foreach ($xmls as &$xml) {
            $xmlName = pathinfo($xml, PATHINFO_FILENAME);
            $abstractPath = sprintf('internal-data://install-upgrade/xmls/%s-%s/%s.xml',
                $profile->profile_id, $product->product_id, $xmlName);

            File::copyFileToAbstractedPath($xml, $abstractPath);
            $xml = [
                'path' => str_replace('internal-data://install-upgrade/xmls/', '', $abstractPath),
                'name' => $xmlName
            ];
        }

        $this->removeTempFiles(null, $directory);
        $this->enableBoard();

        return $xmls;
    }

    /**
     * @param Language $language
     * @return null
     * @throws \Exception
     */
    public function upgradeLanguage(Language $language)
    {
        $this->disableBoard();
        $file = $this->downloadLanguage($language);
        list($directory, $xmls) = $this->extractZip($file);
        $language->updateVersion();
        $this->removeTempFiles($file, $directory);

        $language->fastUpdate('update_available', 0);

        $this->enableBoard();
        return $xmls;
    }

    /**
     * @param AddOn $addOn
     * @throws \Exception
     */
    public function upgradeAddOn(AddOn $addOn)
    {
        $this->disableBoard();
        $file = $this->downloadAddOn($addOn);
        list($directory, $xmls) = $this->extractZip($file);
        $this->createLogEntry($addOn->latest_version, 'addOn', $addOn->addon_id, 'upgrade',
            $addOn->AddOn->version_string);
        $this->removeTempFiles($file, $directory);

        $addOn->fastUpdate('update_available', 0);
        $this->enableBoard();
        return;
    }

    /**
     * @param Style $style
     * @return null
     * @throws \Exception
     */
    public function upgradeStyle(Style $style)
    {
        $this->disableBoard();
        $file = $this->downloadStyle($style);
        list($directory, $xmls) = $this->extractZip($file);
        $style->updateVersion();
        $this->removeTempFiles($file, $directory);

        $style->fastUpdate('update_available', 0);
        $this->enableBoard();
        return $xmls;
    }

    /**
     * @param $currentVersion
     * @param $latestVersion
     * @param string $operator
     * @return mixed
     */
    protected function compareVersions($currentVersion, $latestVersion, $operator = '<')
    {
        return version_compare($this->standardizeVersionNumber($currentVersion),
            $this->standardizeVersionNumber($latestVersion), $operator);
    }

    /**
     * @param $versionNumber
     * @return mixed
     */
    protected function standardizeVersionNumber($versionNumber)
    {
        $versionNumber = str_replace(' ', '', $versionNumber);
        $versionNumber = str_ireplace('Alpha', 'a', $versionNumber);
        $versionNumber = str_ireplace('Beta', 'b', $versionNumber);
        $versionNumber = str_ireplace('ReleaseCandidate', 'rc', $versionNumber);
        $versionNumber = str_ireplace('PatchLevel', 'pl', $versionNumber);
        return $versionNumber;
    }

    /**
     * @param Profile $profile
     * @return array
     * @throws \Exception
     */
    protected function getCredentials(Profile $profile)
    {
        if (!$this->credentials) {
            $profile->setEncryptionSecret($this->secret);
            $this->credentials =  $profile->getCredentials();
        }

        return $this->credentials;
    }

    abstract public function tryAuthentication(Profile $profile);

    abstract public function getProductsFromProvider(Profile $profile);

    abstract public function authenticate(Profile $profile);

    abstract public function checkLanguageForUpdates(Language $language);

    abstract public function checkAddOnForUpdates(AddOn $addOn);

    abstract public function checkStyleForUpdates(Style $style);

    abstract public function downloadLanguage(Language $language);

    abstract public function downloadAddOn(AddOn $addOn);

    abstract public function downloadStyle(Style $style);

    abstract public function getProfile($contentType, $contentId, $data);

    abstract public function getProfileForUrl($url);

    abstract public function createProductFromUrl($url, $type);

    abstract protected function _downloadProduct(Product $product);
}