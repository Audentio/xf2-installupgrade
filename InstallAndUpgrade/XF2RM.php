<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use PHPHtmlParser\Dom\Collection;
use PHPHtmlParser\Dom\HtmlNode;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\LanguageHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\MultiProfile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\TFA;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\AddonHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\EncryptCredentialsTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\HtmlParserTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\HttpClientTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\LanguageHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\StyleHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\TFATrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\VersioningTrait;
use XF\Util\File;

class XF2RM extends AbstractHandler implements StyleHandler, LanguageHandler, AddOnHandler, TFA, MultiProfile, EncryptCredentials
{
    use TFATrait, VersioningTrait, AddonHandlerTrait, HttpClientTrait, EncryptCredentialsTrait, HtmlParserTrait, StyleHandlerTrait, LanguageHandlerTrait;
    /**
     * @var bool
     */
    protected $authenticated = false;

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

            $xfToken = $parser->find('input[name="_xfToken"]')->offsetGet(0)->getAttribute('value');
            $provider = $parser->find('input[name="provider"]')->offsetGet(0)->getAttribute('value');

            $response = $this->httpRequest("{$baseUrl}/login/two-step", [
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
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createAddOnProductFromUrl($url)
    {
        return $this->createProductFromUrl($url, 'addOn');
    }

    public function isValidAddOnUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    /**
     * @param $url
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createLanguageProductFromUrl($url)
    {
        return $this->createProductFromUrl($url, 'language');
    }

    public function isValidLanguageUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    /**
     * @param $url
     * @return Product
     * @throws \XF\PrintableException
     */
    public function createStyleProductFromUrl($url)
    {
        return $this->createProductFromUrl($url, 'style');
    }

    public function isValidStyleUrl($url, &$error)
    {
        return $this->isValidXFRMUrl($url, $error);
    }

    protected function isValidXFRMUrl($url, &$error)
    {
        $this->authenticate();

        $url = explode('/', $url);
        array_pop($url);
        $url = implode('/', $url);

        $pageResult = $this->httpRequest($url);

        if ($pageResult->getStatusCode() !== 200) {
            $error = \XF::phrase('th_installupgrade_connection_failed');
        }

        $parser = $this->htmlParser();

        $parser->load($pageResult->getBody()->getContents());

        /** @noinspection PhpUndefinedMethodInspection */
        /** @var HtmlNode $htmlNode */
        $htmlNode = $parser->find('html')->offsetGet(0);
        $template = $htmlNode->getAttribute('data-template');

        if ($template !== 'xfrm_resource_view') {
            return false;
        }

        /** @var Collection $pageActions */
        $pageActions = $parser->find('.p-title-pageAction > a');

        $validUrl = false;
        foreach ($pageActions as $pageAction) {
            /** @var HtmlNode $pageAction */
            $url = $pageAction->getAttribute('href');

            if (strpos($url, 'http') === 0 && strpos($url, $this->profile->base_url) !== 0) {
                $error = \XF::phrase('th_installupgrade_xfrm_external_download_not_supported');
            } else {
                $validUrl = true;
            }
        }

        return $validUrl;
    }

    /**
     * @param $url
     * @param $type
     * @return Product
     * @throws \XF\PrintableException
     */
    protected function createProductFromUrl($url, $type)
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

    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.xf2rm');
    }

    public function getProfileOptionsTemplate()
    {
        return 'install_upgrade_provider_config_xf2rm';
    }

    public function getProfileDefaultOptions()
    {
        return [
            'base_url' => 'https://xenforo.com/community',
            'page_title' => 'XenForo Community'
        ];
    }

    public function verifyOptions(array $options)
    {
        // TODO: Implement verifyOptions() method.
        return true;
    }
}