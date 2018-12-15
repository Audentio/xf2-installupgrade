<?php

namespace ThemeHouse\InstallAndUpgrade\Provider;

use ThemeHouse\Core\Service\ApiRequest;
use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Util\File;

class ThemeHouse extends AbstractHandler
{
	/** @var string */
	protected $identifier = 'themehouse';
	
    protected $apiKey;
    protected $apiUrl = 'products/{product_id}/download/{version_id}';

    /** @var Profile */
    protected $Profile;
	
	public $isUnique = true;

    public function tryAuthentication(Profile $profile)
    {
        return true;
    }

    /**
     * @param Profile $profile
     */
    public function authenticate(Profile $profile)
    {
        $this->apiKey = $profile->options['api_key'];
    }

    /**
     * @param null $contentType
     * @param null $contentId
     * @param null $dataEntity
     * @return null|Profile|\XF\Mvc\Entity\Entity
     */
    public function getProfile($contentType = null, $contentId = null, $dataEntity = null)
    {
        if (!$this->Profile) {
            $this->Profile = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
                ->where('provider_id', '=', 'themehouse')->fetchOne();
        }

        return $this->Profile;
    }

    /**
     * @param $payload
     * @param $productType
     * @param $profileId
     * @return \XF\Mvc\Entity\Entity
     */
    protected function productFromPayload($payload, $productType, $profileId)
    {
        $product = $this->em->create('ThemeHouse\InstallAndUpgrade:Product');

        $product->bulkSet([
            'profile_id' => $profileId,
            'product_id' => $payload['id'],
            'product_type' => $productType,
            'title' => '[TH] ' . $payload['product_name'],
            'description' => $payload['product_tagline'],
            'version' => $payload['latest_version'],
            'extra' => [
                'thumbnail' => $payload['thumbnail']['thumb']['url'],
                'product_page' => $payload['information_url'],
                'product_id' => $payload['id']
            ]
        ]);

        return $product;
    }

    /**
     * @param Profile $profile
     * @throws \XF\PrintableException
     */
    public function getProductsFromProvider(Profile $profile)
    {
        $this->authenticate($profile);

        \XF::app()->db()->delete('xf_th_installupgrade_product', 'profile_id = ?', [$profile->profile_id]);

        /** @var \ThemeHouse\Core\Service\ApiRequest $apiService */
        $apiService = $this->service('ThemeHouse\Core:ApiRequest');
        $apiService->setApiKey($this->apiKey);

        $addOns = $apiService->get('product-categories/9');
        $installed = $this->finder('ThemeHouse\InstallAndUpgrade:AddOn')
            ->where('addon_id', 'LIKE', 'ThemeHouse/%')
            ->fetch()->pluckNamed('extra');

        $installed = array_map(function ($i) {
            return $i['product_id'];
        }, array_filter($installed, function ($i) {
            return $i && isset($i['product_id']);
        }));

        // Remove XPress from available products to download.
        $installed[] = 234;

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
     * @param Language $language
     * @throws \XF\PrintableException
     * @throws \XF\Mvc\Reply\Exception
     */
    public function checkLanguageForUpdates(Language $language)
    {
        $currentVersion = $language->current_version;
        $product_id = $language->extra['product_id'];
        $latestVersion = $this->getLatestVersion($product_id);

        if (!$latestVersion) {
            return;
        }

        $language->latest_version = $latestVersion;
        $language->update_available = $this->compareVersions($currentVersion, $latestVersion);
        $language->save();
    }

    /**
     * @param AddOn $addOn
     * @throws \XF\PrintableException
     * @throws \XF\Mvc\Reply\Exception
     */
    public function checkAddOnForUpdates(AddOn $addOn)
    {
        $currentVersion = $addOn->AddOn->version_string;
        $product_id = $addOn->extra['product_id'];
        $latestVersion = $this->getLatestVersion($product_id);

        if (!$latestVersion) {
            return;
        }

        $addOn->latest_version = $latestVersion;
        $addOn->update_available = $this->compareVersions($currentVersion, $latestVersion);
        $addOn->save();
    }

    /**
     * @param Style $style
     * @throws \XF\PrintableException
     * @throws \XF\Mvc\Reply\Exception
     */
    public function checkStyleForUpdates(Style $style)
    {
        $currentVersion = $style->current_version;
        $product_id = $style->extra['product_id'];
        $latestVersion = $this->getLatestVersion($product_id);

        if (!$latestVersion) {
            return;
        }

        $style->latest_version = $latestVersion;
        $style->update_available = $this->compareVersions($currentVersion, $latestVersion);
        $style->save();
    }

    /**
     * @param $productId
     * @param $versionId
     * @return array|\GuzzleHttp\Message\FutureResponse|\GuzzleHttp\Message\ResponseInterface|mixed|null|object|\Psr\Http\Message\ResponseInterface
     */
    protected function getVersion($productId, $versionId)
    {
        /** @var \ThemeHouse\Core\Service\ApiRequest $apiService */
        $apiService = $this->service('ThemeHouse\Core:ApiRequest');
        $apiService->setApiKey($this->apiKey);

        $url = str_replace('{product_id}', $productId, str_replace('{version_id}', $versionId, $this->apiUrl));
        return $apiService->get($url);
    }

    /**
     * @param $tempPath
     * @param $productId
     * @param $version
     * @return bool
     */
    protected function downloadFile($tempPath, $productId, $version)
    {
        /** @var ApiRequest $apiRequest */
        $apiRequest = $this->service('ThemeHouse\Core:ApiRequest');
        $apiRequest->setApiKey($this->apiKey);

        $downloadResponse = $this->getVersion($productId, $version);
        if ($downloadResponse['status'] === 'error') {
            $this->app->logException(new \Exception('Unable to download zip from ThemeHouse.'));
            return false;
        }
        $version = $downloadResponse['payload']['version'];
        $downloadResponse = $apiRequest->download($version['download_url'], $tempPath);

        if ($downloadResponse['status'] === 'error') {
            $this->app->logException(new \Exception('Unable to download zip from ThemeHouse.'));
            return false;
        }

        return true;
    }

    /**
     * @param Language $language
     * @return null|string
     */
    public function downloadLanguage(Language $language)
    {
        $tempPath = File::getTempDir() . DIRECTORY_SEPARATOR . 'th-language' . \XF::$time . '.zip';
        $abstractPath = sprintf('internal-data://install-upgrade/languages/th-language-%s-%s.zip',
            $language->extra['product_id'], \XF::$time);

        if ($this->downloadFile($tempPath, $language->extra['product_id'], $language->latest_version)) {
            File::copyFileToAbstractedPath($tempPath, $abstractPath);
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param AddOn $addOn
     * @return null|string
     */
    public function downloadAddOn(AddOn $addOn)
    {
        $tempPath = File::getTempDir() . DIRECTORY_SEPARATOR . 'th-addon' . \XF::$time . '.zip';
        $abstractPath = sprintf('internal-data://install-upgrade/addons/th-addon-%s-%s.zip',
            $addOn->extra['product_id'], \XF::$time);

        if ($this->downloadFile($tempPath, $addOn->extra['product_id'], $addOn->latest_version)) {
            File::copyFileToAbstractedPath($tempPath, $abstractPath);
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param Style $style
     * @return null|string
     */
    public function downloadStyle(Style $style)
    {
        $tempPath = File::getTempDir() . DIRECTORY_SEPARATOR . 'th-style' . \XF::$time . '.zip';
        $abstractPath = sprintf('internal-data://install-upgrade/styles/th-style-%s-%s.zip',
            $style->extra['product_id'], \XF::$time);

        if ($this->downloadFile($tempPath, $style->extra['product_id'], $style->latest_version)) {
            File::copyFileToAbstractedPath($tempPath, $abstractPath);
            return $abstractPath;
        }

        return null;
    }

    /**
     * @param $productId
     * @param bool $getVersionId
     * @return bool|mixed
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function getLatestVersion($productId, $getVersionId = false)
    {
        /** @var ApiRequest $apiRequest */
        $apiRequest = $this->service('ThemeHouse\Core:ApiRequest');

        if (!$this->apiKey) {
            return false;
        }

        $apiRequest->setApiKey($this->apiKey);

        $apiResponse = $apiRequest->get('products/' . $productId);
        if ($apiResponse['status'] === 'error') {
            return false;
        } else {
            $versions = $apiResponse['payload']['versions'];
            $latestVersion = end($versions);

            if ($getVersionId) {
                return $latestVersion['id'];
            } else {
                $latestVersion = $latestVersion['version'];
            }
        }

        return $latestVersion;
    }

    /**
     * @param Product $product
     * @return string
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function _downloadProduct(Product $product)
    {
        $this->authenticate($this->getProfile());

        $version = $this->getLatestVersion($product->product_id, true);

        if (!$version) {
            $this->exception(\XF::phrase('th_iau_no_themehouse_api_key_found'));
        }

        $tempPath = File::getTempDir() . DIRECTORY_SEPARATOR . 'th-' . $product->product_type . \XF::$time . '.zip';

        $this->downloadFile($tempPath, $product->product_id, $version);

        $abstractPath = sprintf('internal-data://install-upgrade/%s/th-%s-%s-%s.zip',
            $product->product_type, $product->product_type, $product->product_id, \XF::$time);
        File::copyFileToAbstractedPath($tempPath, $abstractPath);

        return $abstractPath;
    }

    /**
     * @param $url
     * @return null|Profile|\XF\Mvc\Entity\Entity
     */
    public function getProfileForUrl($url)
    {
        return $this->getProfile();
    }

    /**
     * @param $url
     * @param $type
     * @return bool
     */
    public function createProductFromUrl($url, $type)
    {
        return false;
    }
}