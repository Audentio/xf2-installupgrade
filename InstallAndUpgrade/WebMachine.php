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
 * Class WebMachine
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade
 */
class WebMachine extends AbstractHandler implements ProductList, AddOnHandler
{
    use VersioningTrait, AddonHandlerTrait;

    /**
     * @var string
     */
    public $apiUrl = 'https://wmtech.net/api';
    /**
     * @var string
     */
    public $apiKeyUrl = 'https://wmtech.net/store/account/api-key';
    /**
     * @var string
     */
    protected $licencesUrl = 'https://wmtech.net/store/licenses/';
    /**
     * @var string
     */
    protected $exceptionPrefix = '[WMTech Install & Upgrade]';

    /**
     * @var
     */
    protected $client;


    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.webmachine');
    }

    /**
     * @return string
     */
    public function getProfileOptionsTemplate()
    {
        return 'install_upgrade_provider_config_webmachine';
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
            'categoryIds' => strpos($this->apiUrl, 'http://localhost') !== false ? [3] : [2]
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
                'thumbnail' => $withThumbnail ? 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAIAAABt+uBvAAAEt2lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4KPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iWE1QIENvcmUgNS41LjAiPgogPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4KICA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIgogICAgeG1sbnM6dGlmZj0iaHR0cDovL25zLmFkb2JlLmNvbS90aWZmLzEuMC8iCiAgICB4bWxuczpleGlmPSJodHRwOi8vbnMuYWRvYmUuY29tL2V4aWYvMS4wLyIKICAgIHhtbG5zOnBob3Rvc2hvcD0iaHR0cDovL25zLmFkb2JlLmNvbS9waG90b3Nob3AvMS4wLyIKICAgIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIKICAgIHhtbG5zOnhtcE1NPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvbW0vIgogICAgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIKICAgdGlmZjpJbWFnZUxlbmd0aD0iOTYiCiAgIHRpZmY6SW1hZ2VXaWR0aD0iOTYiCiAgIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiCiAgIHRpZmY6WFJlc29sdXRpb249IjcyLjAiCiAgIHRpZmY6WVJlc29sdXRpb249IjcyLjAiCiAgIGV4aWY6UGl4ZWxYRGltZW5zaW9uPSI5NiIKICAgZXhpZjpQaXhlbFlEaW1lbnNpb249Ijk2IgogICBleGlmOkNvbG9yU3BhY2U9IjEiCiAgIHBob3Rvc2hvcDpDb2xvck1vZGU9IjMiCiAgIHBob3Rvc2hvcDpJQ0NQcm9maWxlPSJzUkdCIElFQzYxOTY2LTIuMSIKICAgeG1wOk1vZGlmeURhdGU9IjIwMjAtMDEt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' : '',
            ]
        ]);

        return $product;
    }

    /**
     * @param $url
     *
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
        return $finder->where('content_id', 'LIKE', 'WMTech/%');
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
                    throw \XF::phrasedException('th_iau_wmtech_expired_licence', ['licenceUrl' => $this->licencesUrl]);
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

        $tempPath = File::getNamedTempFile('wmtech-' . $product->product_type . \XF::$time . '.zip');

        try {
            $client->dbtech_ecommerce->download->downloadFile($downloadableId, $productVersion, $context['type'],
                $tempPath);
        } catch (XFApiException $e) {
            throw new PrintableException($this->exceptionPrefix . ' ' . $e->getMessage());
        }

        return $tempPath;
    }
}
