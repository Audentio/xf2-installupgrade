<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use GuzzleHttp\Cookie\CookieJar;
use PHPHtmlParser\Dom;
use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Util\File;

class Upload extends AbstractHandler
{
    public function tryAuthentication(Profile $profile)
    {
        return true;
    }

    /**
     * Upload constructor.
     * @param null $providerId
     */
    public function __construct($providerId = null)
    {
        parent::__construct($providerId);
    }

    public function getProductsFromProvider(Profile $profile)
    {
        return null;
    }

    public function authenticate(Profile $profile)
    {
        return null;
    }

    public function checkLanguageForUpdates(Language $language)
    {
        return null;
    }

    public function checkAddOnForUpdates(AddOn $addOn)
    {
        return null;
    }

    public function checkStyleForUpdates(Style $style)
    {
        return null;
    }

    public function downloadLanguage(Language $language)
    {
        return null;
    }

    public function downloadAddOn(AddOn $addOn)
    {
        return null;
    }

    public function downloadStyle(Style $style)
    {
        return null;
    }

    public function getProfile($contentType, $contentId, $data)
    {
        return null;
    }

    public function getProfileForUrl($url)
    {
        return null;
    }

    public function createProductFromUrl($url, $type)
    {
        return null;
    }

    /**
     * @param null $product
     * @param $profile
     * @return mixed
     * @throws \Exception
     */
    public function installLanguage($product = null, $profile = null)
    {
        $this->disableBoard();

        $abstractPath = $this->_downloadProduct(null, 'language');
        list($directory, $xmls) = $this->extractZip($abstractPath);

        foreach ($xmls as &$xml) {
            $xmlName = pathinfo($xml, PATHINFO_FILENAME);
            $abstractPath = sprintf('internal-data://install-upgrade/xmls/%s-%s/%s.xml',
                'local', \XF::$time, $xmlName);

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
     * @throws \Exception
     */
    public function installAddOn($product = null, $profile = null)
    {
        $this->disableBoard();
        $abstractPath = $this->_downloadProduct(null, 'addOn');

        $tempFile = File::copyAbstractedPathToTempFile($abstractPath);

        $zip = $this->openZip($tempFile);

        $addonId = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if (strpos($fileName, 'addon.json') !== false) {
                preg_match('/src\/addons\/(.*)\/addon.json$/', $fileName, $matches);

                if(!isset($matches[1])) {
                    $this->exception(\XF::phrase('th_iau_no_addon_file_found'));
                }

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
        $this->enableBoard();

        return $addonId;
    }

    /**
     * @param $productId
     * @param $profile
     * @return mixed
     * @throws \Exception
     */
    public function installStyle($product = null, $profile = null)
    {
        $this->disableBoard();

        $abstractPath = $this->_downloadProduct(null, 'style');
        list($directory, $xmls) = $this->extractZip($abstractPath);

        foreach ($xmls as &$xml) {
            $xmlName = pathinfo($xml, PATHINFO_FILENAME);
            $abstractPath = sprintf('internal-data://install-upgrade/xmls/%s-%s/%s.xml',
                'local', \XF::$time, $xmlName);

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
     * @param Product|null $product
     * @param null $type
     * @return string
     */
    protected function _downloadProduct(Product $product = null, $type = null)
    {
        $request = \XF::app()->request();

        $upload = $request->getFile('upload_zip', false)->getTempFile();

        $abstractPath = sprintf('internal-data://install-upgrade/%s/upload-%s.zip',
            $type, \XF::$time);
        File::copyFileToAbstractedPath($upload, $abstractPath);

        return $abstractPath;
    }
}