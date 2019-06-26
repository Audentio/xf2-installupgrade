<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use ThemeHouse\Core\Service\ApiRequest;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\ProductList;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\AddonHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\StyleHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\VersioningTrait;
use XF\Util\File;

/**
 * Class ThemeHouse
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade
 */
class ThemeHouse extends AbstractHandler implements StyleHandler, AddOnHandler, ProductList
{
    use VersioningTrait, AddonHandlerTrait, StyleHandlerTrait;

    /**
     * @var string
     */
    protected $apiUrl = 'products/{product_id}/download/{version_id}';

    /**
     * @param $url
     * @param null $error
     * @throws \Exception
     */
    public function createAddOnProductFromUrl($url, &$error = null)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new \Exception('This provider does not support installation from URL');
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    public function isValidAddOnUrl($url, &$error)
    {
        return false;
    }

    /**
     * @param $url
     * @param null $error
     * @throws \Exception
     */
    public function createStyleProductFromUrl($url, &$error = null)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new \Exception('This provider does not support installation from URL');
    }

    /**
     * @param $url
     * @param $error
     * @return bool
     */
    public function isValidStyleUrl($url, &$error)
    {
        return false;
    }

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.themehouse');
    }

    /**
     * @return string
     */
    public function getProfileOptionsTemplate()
    {
        return 'install_upgrade_provider_config_themehouse';
    }

    /**
     * @return array
     */
    public function getProfileDefaultOptions()
    {
        return [];
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
     * @throws \XF\PrintableException
     */
    public function getProducts()
    {
        $profile = $this->profile;

        /** @var \ThemeHouse\Core\Service\ApiRequest $apiService */
        $apiService = $this->service('ThemeHouse\Core:ApiRequest');
        $apiService->setApiKey($this->getApiKey());

        $addOns = $apiService->get('product-categories/9');
        $installed = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('content_id', 'LIKE', 'ThemeHouse/%')
            ->fetch()->pluckNamed('extra');

        $installed = array_map(function ($i) {
            return $i['product_id'];
        }, array_filter($installed, function ($i) {
            return $i && isset($i['product_id']);
        }));

        // Remove XF2.0 Products from list
        $installed[] = 211; // Bookmarks
        $installed[] = 212; // Reactions

        if (isset($addOns['payload']['products']) && !empty($addOns['payload']['products'])) {
            foreach ($addOns['payload']['products'] as $addOn) {
                if (!in_array($addOn['id'], $installed)) {
                    $this->productFromPayload($addOn, 'addOn', $profile->profile_id)->save();
                }
            }
        }

        $styles = $apiService->get('product-categories/8');
        if (isset($styles['payload']['products']) && !empty($styles['payload']['products'])) {
            foreach ($styles['payload']['products'] as $style) {
                $this->productFromPayload($style, 'style', $profile->profile_id)->save();
            }
        }
    }

    /**
     * @param $payload
     * @param $productType
     * @param $profileId
     * @return \XF\Mvc\Entity\Entity
     */
    protected function productFromPayload($payload, $productType, $profileId)
    {
        $product = $this->em->find('ThemeHouse\InstallAndUpgrade:Product', [
            $profileId,
            $payload['id']
        ]);

        if (!$product) {
            $product = $this->em->create('ThemeHouse\InstallAndUpgrade:Product');
        }

        $product->bulkSet([
            'profile_id' => $profileId,
            'product_id' => $payload['id'],
            'product_type' => $productType,
            'title' => '[TH] ' . $payload['product_name'],
            'description' => $payload['product_tagline'] ?: '',
            'latest_version' => $payload['latest_version'],
            'extra' => [
                'thumbnail' => $payload['thumbnail']['thumb']['url'],
                'product_page' => $payload['information_url'],
                'product_id' => $payload['id']
            ]
        ]);

        return $product;
    }

    /**
     * @param Product $product
     * @return bool|null|string
     */
    protected function downloadProduct(Product $product)
    {
        $productId = $product->extra['product_id'];
        $versionId = $this->getLatestVersion($product, true);

        $tempFile = File::getTempFile();

        /** @var ApiRequest $apiRequest */
        $apiRequest = $this->service('ThemeHouse\Core:ApiRequest');
        $apiRequest->setApiKey($this->getApiKey());

        $downloadResponse = $this->getVersion($productId, $versionId);
        if ($downloadResponse['status'] === 'error') {
            $this->app->logException(new \Exception('Unable to download zip from ThemeHouse.'));
            return false;
        }

        $version = $downloadResponse['payload']['version'];
        $downloadResponse = $apiRequest->download($version['download_url'], $tempFile);

        if ($downloadResponse['status'] === 'error') {
            $this->app->logException(new \Exception('Unable to download zip from ThemeHouse.'));
            return null;
        }

        return $tempFile;
    }

    /**
     * @param $product
     * @param bool $getVersionId
     * @return string
     */
    protected function getLatestVersion($product, $getVersionId = false)
    {
        /** @var ApiRequest $apiRequest */
        $apiRequest = $this->service('ThemeHouse\Core:ApiRequest');

        if (!$this->getApiKey()) {
            return '';
        }

        $apiRequest->setApiKey($this->getApiKey());

        $apiResponse = $apiRequest->get('products/' . $product->product_id);
        if ($apiResponse['status'] === 'error') {
            return '';
        } else {
            $versions = $apiResponse['payload']['versions'];
            $latestVersion = end($versions);

            return $getVersionId ? $latestVersion['id'] : $latestVersion['version'];
        }
    }

    /**
     * @return null
     */
    protected function getApiKey()
    {
        return isset($this->profile->options['api_key']) ? $this->profile->options['api_key'] : null;
    }

    /**
     * @param $productId
     * @param $versionId
     * @return array|mixed|object|\Psr\Http\Message\ResponseInterface
     */
    protected function getVersion($productId, $versionId)
    {
        /** @var \ThemeHouse\Core\Service\ApiRequest $apiService */
        $apiService = $this->service('ThemeHouse\Core:ApiRequest');
        $apiService->setApiKey($this->getApiKey());

        $url = str_replace('{product_id}', $productId, str_replace('{version_id}', $versionId, $this->apiUrl));
        return $apiService->get($url);
    }
}