<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use PHPHtmlParser\Dom\Collection;
use PHPHtmlParser\Dom\HtmlNode;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\LanguageHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\MultiProfile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\TFA;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\AddonHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\EncryptCredentialsTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\HtmlParserTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\HttpClientTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\LanguageHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\StyleHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\TFATrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\VersioningTrait;
use XF\Util\File;

/**
 * Class XF2RM
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade
 */
class XF2RM extends AbstractHandler implements StyleHandler, LanguageHandler, AddOnHandler, TFA, MultiProfile, EncryptCredentials
{
    use TFATrait, VersioningTrait, AddonHandlerTrait, HttpClientTrait, EncryptCredentialsTrait, HtmlParserTrait, StyleHandlerTrait, LanguageHandlerTrait;
    /**
     * @var bool
     */
    protected $authenticated = false;

    /**
     * @param $url
     * @param null $error
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createAddOnProductFromUrl($url, &$error = null)
    {
        return $this->createProductFromUrl($url, 'addOn', $error);
    }

    /**
     * @param $url
     * @param $type
     * @param $
     * @param null $error
     * @return Product
     * @throws \XF\PrintableException
     */
    protected function createProductFromUrl($url, $type, &$error = null)
    {
        preg_match('/(\d+)/', strrev($url), $matches);
        while (is_array($matches)) {
            $matches = array_shift($matches);
        }
        $id = strrev($matches);

        /** @var Product $product */
        if ($product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product', [
            $this->profile->profile_id,
            $id
        ])) {
            return $product;
        }

        $this->authenticate();

        $url = explode('/', $url);
        array_pop($url);
        $url = implode('/', $url);

        $response = $this->httpRequest($url);
        $parser = $this->htmlParser();
        $parser->load($response->getBody()->getContents());

        /** @var Collection $titleMeta */
        $titleMeta = $parser->find('meta[property="og:title"]');
        $title = $titleMeta->offsetExists(0) ? html_entity_decode($titleMeta->offsetGet(0)->getAttribute('content')) : 'N/A';

        /** @var Collection $versionContainer */
        $versionContainer = $parser->find('.p-title-value > span.u-muted');
        $version = $versionContainer->offsetExists(0) ? $versionContainer->offsetGet(0)->text() : 'N/A';

        /** @var Product $product */
        $product = $this->em()->create('ThemeHouse\InstallAndUpgrade:Product');

        $product->bulkSet([
            'profile_id' => $this->profile->profile_id,
            'product_id' => $id,
            'product_type' => $type,
            'title' => $title,
            'description' => '',
            'latest_version' => $version,
            'extra' => [
                'download_url' => $url . '/download/'
            ]
        ]);

        $product->save();

        return $product;
    }

    /**
     * @return bool
     */
    protected function authenticate()
    {
        if ($this->authenticated) {
            return true;
        }

        $credentials = $this->getCredentials();

        $baseUrl = $this->profile->base_url;

        /* Session Cookie */
        $this->httpRequest("{$baseUrl}/login/login");

        /* Login */
        $response = $this->httpRequest("{$baseUrl}/login/login", [
            'form_params' => [
                'login' => $credentials['user'],
                'password' => $credentials['password']
            ]
        ], 'post');

        if ($this->profile->has_tfa) {
            $parser = $this->htmlParser();
            $parser->load($response->getBody()->getContents());

            $xfToken = $parser->find('input[name="_xfToken"]')
                ->offsetGet(0)->getAttribute('value');

            $provider = $parser->find('input[name="provider"]')
                ->offsetGet(0)->getAttribute('value');

            $this->httpRequest("{$baseUrl}/login/two-step", [
                'form_params' => [
                    'code' => $this->getTfaCode(),
                    'confirm' => 1,
                    'remember' => 1,
                    'provider' => $provider,
                    '_xfRedirect' => $baseUrl,
                    '_xfToken' => $xfToken
                ]
            ], 'post');
        }

        $this->authenticated = true;
        return true;
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    public function isValidAddOnUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    protected function isValidXFRMUrl($url, &$error)
    {
        $this->authenticate();

        $url = explode('/', $url);
        array_pop($url);
        $url = implode('/', $url);

        $pageResult = $this->httpRequest($url);

        if ($pageResult->getStatusCode() !== 200) {
            $error = \XF::phrase('th_installupgrade_connection_failed');
            return false;
        }

        $parser = $this->htmlParser();

        $parser->load($pageResult->getBody()->getContents());

        /** @noinspection PhpUndefinedMethodInspection */
        /** @var HtmlNode $htmlNode */
        $htmlNode = $parser->find('html')->offsetGet(0);
        $template = $htmlNode->getAttribute('data-template');

        if ($template !== 'xfrm_resource_view') {
            $error = \XF::phrase('th_installupgrade_xfrm_url_does_not_appear_to_be_resource_overview_page', ['url' => $url]);
            return false;
        }

        /** @var Collection $pageActions */
        $pageActions = $parser->find('.p-title-pageAction > a');

        foreach ($pageActions as $pageAction) {
            /** @var HtmlNode $pageAction */
            $url = $pageAction->getAttribute('href');
            $classes = $pageAction->getAttribute('class');

            if (strpos($classes, 'button--icon--redirect')) {
                $error = \XF::phrase('th_installupgrade_xfrm_external_download_not_supported');
                return false;
            }

            if (strpos($url, 'http') === 0 && strpos($url, $this->profile->base_url) !== 0) {
                return false;
            }
            
            if($pageAction->getAttribute('data-xf-click')) {
                $error = \XF::phrase('th_installupgrade_xfrm_multiple_downloads_not_supported');
                return false;
            }


            if (strpos($classes, 'button--icon--download')) {
                return true;
            }
        }

        $error = \XF::phrase('th_installupgrade_xfrm_no_download_found', ['url' => $url]);
        return false;
    }

    /**
     * @param $url
     * @param null $error
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createLanguageProductFromUrl($url, &$error = null)
    {
        return $this->createProductFromUrl($url, 'language', $error);
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    public function isValidLanguageUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    /**
     * @param $url
     * @param null $error
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createStyleProductFromUrl($url, &$error = null)
    {
        return $this->createProductFromUrl($url, 'style', $error);
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    public function isValidStyleUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.xf2rm');
    }

    /**
     * @return string
     */
    public function getProfileOptionsTemplate()
    {
        return 'install_upgrade_provider_config_xf2rm';
    }

    /**
     * @return array
     */
    public function getProfileDefaultOptions()
    {
        return [
            'base_url' => 'https://xenforo.com/community',
            'page_title' => 'XenForo Community'
        ];
    }

    /**
     * @param array $options
     * @return bool
     */
    public function verifyOptions(array $options)
    {
        return true;
    }

    /**
     * @param Product $product
     * @return string
     * @throws \Exception
     */
    protected function downloadProduct(Product $product)
    {
        if (!isset($product->extra['download_url'])) {
            throw new \Exception('Download URL not found in product');
        }

        $this->authenticate();

        $tempFile = File::getNamedTempFile('install-upgrade.zip');
        File::writeFile($tempFile, $this->httpRequest($product->extra['download_url'])->getBody());
        return $tempFile;
    }

    /**
     * @param Product $product
     * @return string
     */
    protected function getLatestVersion(Product $product)
    {
        if (!isset($product->extra['download_url'])) {
            return '0.0.0';
        }

        $url = $product->extra['download_url'];
        $url = substr($url, 0, -10);

        $response = $this->httpRequest($url);
        $parser = $this->htmlParser();
        $parser->load($response->getBody()->getContents());

        /** @var Collection $versionContainer */
        $versionContainer = $parser->find('.p-title-value > span.u-muted');
        return $versionContainer->offsetExists(0) ? $versionContainer->offsetGet(0)->text() : '0.0.0';
    }
}