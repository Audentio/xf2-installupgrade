<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\ProductList;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\AddonHandlerTrait;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\VersioningTrait;
use XF\Db\DuplicateKeyException;
use XF\Mvc\Entity\Finder;
use XF\PrintableException;
use XF\Util\File;
use XFApi\Client;
use XFApi\Dto\DBTech\eCommerce\ProductDto;
use XFApi\Exception\XFApiException;

/**
 * Class DragonByte
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade
 */
class DragonByte extends AbstractHandler implements ProductList, AddOnHandler
{
    use VersioningTrait, AddonHandlerTrait;

    /**
     * @var string
     */
    public $apiUrl = 'https://www.dragonbyte-tech.com/api';
    /**
     * @var string
     */
    public $apiKeyUrl = 'https://www.dragonbyte-tech.com/store/account/api-key';
    /**
     * @var string
     */
    protected $licencesUrl = 'https://www.dragonbyte-tech.com/dbtech-ecommerce/licenses/';
    /**
     * @var string
     */
    protected $exceptionPrefix = '[DragonByte Install & Upgrade]';

    /**
     * @var
     */
    protected $client;


    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.dragonbyte');
    }

    /**
     * @return string
     */
    public function getProfileOptionsTemplate()
    {
        return 'install_upgrade_provider_config_dragonbyte';
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
     *
     * @return bool
     * @throws PrintableException
     */
    public function verifyOptions(array $options)
    {
        $client = $this->getApiClient($options['api_key']);

        try {
            $client->xf->index->get('/');
        } catch (XFApiException $e) {
            throw new PrintableException($e->getMessage());
        }

        return true;
    }

    /**
     * @param string|null $apiKey
     *
     * @return Client
     */
    protected function getApiClient($apiKey = null)
    {
        if (!$this->client) {
            $this->client = new Client($this->apiUrl, $apiKey ?: $this->getApiKey());
        }

        return $this->client;
    }

    /**
     * @return string|null
     */
    protected function getApiKey()
    {
        return isset($this->profile->options['api_key']) ? $this->profile->options['api_key'] : null;
    }

    /**
     */
    public function getProducts()
    {
        $installed = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('profile_id', '=', $this->profile->profile_id)
            ->keyedBy('product_id')
            ->fetch();

        $client = $this->getApiClient();

        try {
            $context = $this->getContext();
            $addOns = $client->dbtech_ecommerce->product->getPurchases($context['categoryIds'], $context['platforms']);
        } catch (XFApiException $e) {
            switch ($e->getCode()) {
                case 402:
                    $this->logProfileError('addOns', $e->getMessage());
                    break;

                default:
                    \XF::logException($e);
                    break;
            }

            return;
        }

        if (empty($addOns)) {
            return;
        }

        /** @var ProductDto $addOn */
        foreach ($addOns as $addOn) {
            /** @var Product $product */
            $product = isset($installed[$addOn->product_id])
                ? $installed[$addOn->product_id] :
                $product = $this->em->create('ThemeHouse\InstallAndUpgrade:Product');

            $product = $this->createProductFromProductDto($product, $addOn);

            if ($product && $product->preSave()) {
                try {
                    $product->saveIfChanged();
                } /** @noinspection PhpRedundantCatchClauseInspection */
                catch (DuplicateKeyException $e) {
                    // race, just ignore
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function getContext()
    {
        // Rather than a bunch of if statements, we'll just construct the product filter this way
        $version = explode('.', \XF::$version);

        return [
            'platforms' => ['xf' . $version[0] . $version[1]],
            'type' => 'full', // We don't support demo downloads
            'categoryIds' => strpos($this->apiUrl, 'http://localhost') !== false ? [1, 2] : [5]
        ];
    }

    /**
     * @param Product $product
     * @param ProductDto $addOn
     * @param bool $withThumbnail
     * @return Product
     */
    protected function createProductFromProductDto(
        Product $product,
        ProductDto $addOn,
        $withThumbnail = true
    ) {
        $product->bulkSet([
            'profile_id' => $this->profile->profile_id,
            'product_id' => $addOn->product_id,
            'product_type' => 'addOn',
            'title' => $addOn->full_title,
            'description' => $addOn->tagline,
            'latest_version' => $addOn->LatestVersion['version_string'],
            'extra' => [
                'product_page' => $addOn->product_page_url,
                'product_id' => $addOn->product_id,
                'thumbnail' => $withThumbnail ? 'data:image/png;base64,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' : '',
            ]
        ]);

        return $product;
    }

    /**
     * @param $url
     *
     * @throws \Exception
     */
    public function createAddOnProductFromUrl($url)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        throw new \Exception('This provider does not support installation from URL');
    }

    /**
     * @param $url
     * @param $error
     *
     * @return bool
     */
    public function isValidAddOnUrl($url, &$error)
    {
        return false;
    }

    /**
     * @param Finder $finder
     * @return Finder
     */
    protected function getFilteredProducts(Finder $finder)
    {
        return $finder->where('content_id', 'LIKE', 'DBTech/%');
    }

    /**
     * @param $product
     * @param bool $getVersionId
     *
     * @return int|string
     */
    protected function getLatestVersion($product, $getVersionId = false)
    {
        $client = $this->getApiClient();
        try {
            $context = $this->getContext();

            /** @var \XFApi\Dto\DBTech\eCommerce\DownloadDto $latestVersion */
            $latestVersion = $client->dbtech_ecommerce->product->getLatestVersion($product->product_id,
                $context['platforms'], $context['type']);
        } catch (XFApiException $e) {
            switch ($e->getCode()) {
                case 404:
                    $this->logProfileError('addOns', $e->getMessage());
                    break;

                default:
                    \XF::logException($e);
                    break;
            }

            return '';
        }

        return $getVersionId ? $latestVersion->download_id : $latestVersion->version_string;
    }

    /**
     * @param Product $product
     *
     * @return mixed
     * @throws PrintableException
     */
    protected function downloadProduct(Product $product)
    {
        $downloadableId = null;
        $productVersion = null;
        $client = $this->getApiClient();
        $context = $this->getContext();

        if ($product->update_available) {
            foreach (\array_reverse($context['platforms']) as $_productVersion) {
                try {
                    /** @var \XFApi\Dto\DBTech\eCommerce\DownloadDto $latestVersion */
                    $latestVersion = $client->dbtech_ecommerce->product->getLatestVersion($product->product_id,
                        $_productVersion, $context['type']);
                } catch (XFApiException $e) {
                    // product version does not exist
                    if ($e->getCode() === 404) {
                        continue;
                    }
                }

                if (!$latestVersion->can_download) {
                    throw \XF::phrasedException('th_iau_dbtech_expired_licence', ['licenceUrl' => $this->licencesUrl]);
                }

                $downloadableId = $latestVersion->download_id;
                $productVersion = $_productVersion;
                break;
            }
        } else {
            foreach (\array_reverse($context['platforms']) as $_productVersion) {
                $page = 0;
                do {
                    $page++;

                    try {
                        /** @var \XFApi\Dto\DBTech\eCommerce\DownloadsDto $downloads */
                        $downloads = $client->dbtech_ecommerce->product->getDownloads($product->product_id,
                            $_productVersion, $context['type'], $page);
                    } catch (XFApiException $e) {
                        // product version does not exist
                        if ($e->getCode() === 404) {
                            break;
                        }
                        throw new PrintableException($this->exceptionPrefix . ' ' . $e->getMessage());
                    }

                    /** @var \XFApi\Dto\DBTech\eCommerce\DownloadDto $download */
                    foreach ($downloads as $download) {
                        if ($download->can_download) {
                            $productVersion = $_productVersion;
                            $downloadableId = $download->download_id;
                            break 3;
                        }
                    }
                } while ($downloads->pagination->current_page < $downloads->pagination->last_page);
            }
        }

        if (!$downloadableId || !$productVersion) {
            throw new PrintableException($this->exceptionPrefix . ' No downloadable versions could be found.');
        }

        $tempPath = File::getNamedTempFile('dbtech-' . $product->product_type . \XF::$time . '.zip');

        try {
            $client->dbtech_ecommerce->download->downloadFile($downloadableId, $productVersion, $context['type'],
                $tempPath);
        } catch (XFApiException $e) {
            throw new PrintableException($this->exceptionPrefix . ' ' . $e->getMessage());
        }

        return $tempPath;
    }
}