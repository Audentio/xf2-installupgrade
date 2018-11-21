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

class XF2RM extends AbstractHandler
{
    /** @var bool */
    protected $authenticated = false;

    public $supportsProductList = false;

    public function canEncryptCredentials()
    {
        return true;
    }

    public function getEncryptedOptions()
    {
        return [
            'password',
        ];
    }

    /**
     * @param Profile $profile
     * @return bool
     * @throws \Exception
     */
    public function tryAuthentication(Profile $profile)
    {
        // Cannot test for tfa profiles
        if($profile->has_tfa) {
            return true;
        }

        if (!$this->authenticate($profile)) {
            return false;
        }

        return true;
    }

    /**
     * @param Profile $profile
     * @return null
     * @throws \Exception
     */
    public function getProductsFromProvider(Profile $profile)
    {
        #$this->authenticate($profile);
        return null;
    }

    /**
     * @param Profile $profile
     * @return bool
     * @throws \Exception
     */
    public function authenticate(Profile $profile)
    {
        if ($this->authenticated) {
            return true;
        }
        $credentials = $this->getCredentials($profile);

        $baseUrl = $profile->base_url;

        /* Session Cookie */
        $this->httpRequest("{$baseUrl}/login/login");

        /* Login */
        $response = $this->httpRequest("{$baseUrl}/login/login", [
            'body' => [
                'login' => $credentials['user'],
                'password' => $credentials['password']
            ]
        ], 'post');

        if($profile->has_tfa) {
            $dom = new Dom();
            $dom->load($response->getBody()->getContents());

            $response = $this->httpRequest("{$baseUrl}/login/two-step", [
                'body' => [
                    'code' => $this->tfaKey,
                    'confirm' => 1,
                    'remember' => 1,
                    '_xfRedirect' => $baseUrl,
                    '_xfToken' => $dom->find('input[name="_xfToken"]')[0]->getAttribute('value'),
                    'provider' => $dom->find('input[name="provider"]')[0]->getAttribute('value')
                ]
            ], 'post');
        }

        $this->authenticated = true;
        return true;
    }

    /**
     * @param $url
     * @return string
     * @throws \Exception
     */
    protected function getVersionFromUrl($url)
    {
//        $profile = $this->getProfileForUrl($url);
//        $this->authenticate($profile);

        $page = $this->httpRequest($url);

        $dom = new Dom();
        $dom->load($page->getBody()->getContents());

        /** @var Dom\HtmlNode $versionElement */
        $versionElement = $dom->find('.p-title-value .u-muted')[0];

        return $versionElement->text();
    }

    /**
     * @param Language $language
     * @throws \Exception
     */
    public function checkLanguageForUpdates(Language $language)
    {
        $language->latest_version = $this->getVersionFromUrl($language->download_url);
        $language->update_available = $this->compareVersions($language->current_version, $language->latest_version);
        $language->saveIfChanged();
    }

    /**
     * @param AddOn $addOn
     * @throws \Exception
     */
    public function checkAddOnForUpdates(AddOn $addOn)
    {
        $addOn->latest_version = $this->getVersionFromUrl($addOn->download_url);
        $addOn->update_available = $this->compareVersions($addOn->AddOn->version_string, $addOn->latest_version);
        $addOn->saveIfChanged();
    }

    /**
     * @param Style $style
     * @throws \Exception
     */
    public function checkStyleForUpdates(Style $style)
    {
        $style->latest_version = $this->getVersionFromUrl($style->download_url);
        $style->update_available = $this->compareVersions($style->current_version, $style->latest_version);
        $style->saveIfChanged();
    }

    /**
     * @param Language $language
     * @return null|string
     * @throws \Exception
     */
    public function downloadLanguage(Language $language)
    {
        $abstractPath = sprintf('internal-data://install-upgrade/languages/xf2rm-language-%s-%s.zip',
            $language->language_id, \XF::$time);

        if ($this->downloadFile($language->download_url, $abstractPath)) {
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param AddOn $addOn
     * @return null|string
     * @throws \Exception
     */
    public function downloadAddOn(AddOn $addOn)
    {
        $abstractPath = sprintf('internal-data://install-upgrade/addOns/xf2rm-addOn-%s-%s.zip',
            $addOn->addon_id_url, \XF::$time);

        if ($this->downloadFile($addOn->download_url, $abstractPath)) {
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param Style $style
     * @return null|string
     * @throws \Exception
     */
    public function downloadStyle(Style $style)
    {
        $abstractPath = sprintf('internal-data://install-upgrade/styles/xf2rm-style-%s-%s.zip',
            $style->style_id, \XF::$time);

        if ($this->downloadFile($style->download_url, $abstractPath)) {
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param $contentType
     * @param $contentId
     * @param $data
     * @return mixed|null
     */
    public function getProfile($contentType, $contentId, $data)
    {
        return $this->getProfileForUrl($data->download_url);
    }

    /**
     * @param $downloadUrl
     * @param $abstractPath
     * @return bool
     * @throws \Exception
     */
    protected function downloadFile($downloadUrl, $abstractPath)
    {
//        $pageResponse = $this->httpRequest($downloadUrl);
        $this->authenticate($this->getProfileForUrl($downloadUrl));
        $response = $this->httpRequest("{$downloadUrl}/download");

        $tempFile = File::getTempFile();
        File::writeFile($tempFile, $response->getBody());
        File::copyFileToAbstractedPath($tempFile, $abstractPath);

        return true;
    }

    /**
     * @param Product $product
     * @return string
     * @throws \Exception
     */
    protected function _downloadProduct(Product $product)
    {
        $this->authenticate($product->Profile);

        $downloadUrl = trim($product->extra['download_url'], "/");
        $abstractPath = sprintf('internal-data://install-upgrade/addons/%s-%s-%s-%s.zip',
            $product->profile_id, $product->product_type, $product->product_id, \XF::$time);

        $this->downloadFile($downloadUrl, $abstractPath);

        return $abstractPath;
    }

    /**
     * @param $url
     * @return mixed|null
     */
    public function getProfileForUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->where('provider_id', '=', 'xf2rm')
            ->fetch();

        foreach ($profiles as $profile) {
            if (strpos($url, $profile->base_url) !== false) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * @param $url
     * @param $type
     * @return \Exception|\XF\Mvc\Entity\Entity
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function createProductFromUrl($url, $type)
    {
        $profile = $this->getProfileForUrl($url);
        $this->authenticate($profile);

        $page = $this->httpRequest($url);

        $dom = new Dom();

        $dom->load($page->getBody()->getContents());

        /** @var Dom\HtmlNode $titleElement */
        $titleElement = $dom->find('.p-title-value')[0];
        if (!$titleElement) {
            return new \Exception(\XF::phrase('th_iau_page_format_not_recognized_may_not_be_resource'));
        }
        $title = trim($titleElement->getChildren()[0]);

        /** @var Dom\HtmlNode $versionElement */
        $versionElement = $dom->find('.p-title-value .u-muted')[0];
        if (!$versionElement) {
            return new \Exception(\XF::phrase('th_iau_page_format_not_recognized_may_not_be_resource'));
        }
        $version = $versionElement->text();

        preg_match('/resources\/[^\.]+\.(\d+)/', $url, $matches);
        $productId = $matches[1];

        /** @var Dom\HtmlNode $pageActions */
        $pageActions = $dom->find('.p-title-pageAction')[0];
        $buttons = $pageActions->getChildren();

        $downloadUrl = false;
        foreach ($buttons as $downloadButton) {
            /** @var Dom\HtmlNode $downloadButton */
            $href = $downloadButton->getAttribute('href');
            if (strpos($href, 'http') === false && strpos($href, 'download') !== false) {
                $downloadUrl = true;
                break;
            }
        }

        if (!$downloadUrl) {
            return new \Exception(\XF::phrase('th_iau_no_direct_download_url_found'));
        }

        $key = [$profile->profile_id, $productId];
        $product = $this->em->find('ThemeHouse\InstallAndUpgrade:Product', $key);

        if (!$product) {
            $product = $this->em->create('ThemeHouse\InstallAndUpgrade:Product');
        }

        $product->bulkSet([
            'title' => $title,
            'product_type' => $type,
            'profile_id' => $profile->profile_id,
            'product_id' => $productId,
            'version' => $version,
            'extra' => [
                'download_url' => $url
            ]
        ]);

        $product->save();
        return $product;
    }
}