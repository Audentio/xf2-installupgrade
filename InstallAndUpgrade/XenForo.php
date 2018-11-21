<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use GuzzleHttp\Cookie\CookieJar;
use PHPHtmlParser\Dom;
use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Mvc\Reply\Exception;
use XF\Util\File;

class XenForo extends AbstractHandler
{
    // https://installandupgradeapi.themehouse.com/latestVersions.json
    /** @var bool */
    protected $authenticated = false;

    /** @var Profile */
    protected $Profile;

    /** @var mixed */
    protected $libxml_previous_state;

    /** @var int */
    protected $libxml_stop_count = 0;

    protected $products;

    protected $addOnMap = [
        'XF' => 'xenforo',
        'XFI' => 'xfi',
        'XFES' => 'xfes',
        'XFMG' => 'xfmg',
        'XFRM' => 'xfresource'
    ];

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
        if (!$this->authenticate($profile)) {
            return false;
        }

        try {
            $this->getLicenseId($profile);
        }
        catch(Exception $e) {
            return $e;
        }

        return true;
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
                ->where('provider_id', '=', 'xenforo')->fetchOne();
        }

        return $this->Profile;
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

        /* Session Cookie */
        $this->httpRequest('https://xenforo.com/customers');

        /* Login */
        $response = $this->httpRequest('https://xenforo.com/customers/login', [
            'body' => [
                'email' => $credentials['email'],
                'password' => $credentials['password']
            ]
        ], 'post');

        $this->authenticated = true;
        return true;
    }

    /**
     * @param Profile $profile
     * @throws \Exception
     */
    public function getProductsFromProvider(Profile $profile)
    {
        $this->authenticate($profile);

        \XF::app()->db()->delete('xf_th_installupgrade_product', 'profile_id = ?', [$profile->profile_id]);
        $licenseId = $this->getLicenseId();
        $licenseIdParam = "l={$licenseId}";
        $response = $this->httpRequest('https://xenforo.com/customers/');
        $dom = new Dom();
        $dom->load($response->getBody()->getContents());
        $downloadLinks = $dom->find('.downloadLink a');
        $products = [];
        $addOnCache = $this->app->container('addon.cache');
        foreach ($downloadLinks as $downloadLink) {
            /** @var Dom\HtmlNode $downloadLink */
            $link = $downloadLink->getAttribute('href');
            if (strpos($link, $licenseIdParam) !== false) {
                preg_match('/d=([^&]*)/', $link, $matches);
                if ($matches[1] == 'xenforo') {
                    continue;
                }
                $addOnId = array_search($matches[1], $this->addOnMap);
                if (isset($addOnCache[$addOnId])) {
                    continue;
                }
                /** @var Product $product */
                $product = $this->em->create('ThemeHouse\InstallAndUpgrade:Product');
                $product->product_type = 'addOn';
                $product->profile_id = $profile->profile_id;
                $product->product_id = $matches[1];
                $product->title = "XenForo " . substr($downloadLink->text(), 9);
                $products[] = $product;
            }
        }

        foreach ($products as &$product) {
            $version = $this->checkProductForUpdate($product->product_id, 'both');
            $product->version = $version['string'];
            $product->extra = [
                'version_id' => $version['id'],
                'thumbnail' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAADICAMAAACahl6sAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAB7UExURQBillOWvSl8qpvE30CJsL/Y5Xyw0f///6XK5ICxywppmxBsnYa31RVvoEiQuGCdvd/r8jB/qiB2o3GqzI+60e/1+B92pV2dws/i65/E2Gejx1CTtz6JszSDrnCnxK/O3pC92myoyTaFsFGWvEONtl6fww1rnIa51htzo9b7EWAAAAQiSURBVHja7Zhrd6IwEIZRAspVUBC8VNvavfz/X7iZ3AwQFXV7utvzPh9qbqdnXjKTmcTzAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJ4icFH9h0JmLgII+Voh7XdxLfYdgh1CIARC/m0hU0527mbUN+2JIJ/G9vK1+M3FlDVzVUjJIglb6qGEMeqU0ZEf01FpLWYLuXbBEjnwfvr48H6ergtJ5/P5qzEn9nl3JVqT1/mZNFcLeDv0sq1vZsLpTSHLqrZyZBFJ8xhvR8vApE5lNDvaCbVdiMG391/e6e26kDXZNNG9Le/4JGudzruksRGy9jsz+xtCWN1L93WphRysqVYsrga1QSKEfJx+3BDi5WTMWjkOtV/0RnFNISHtDrWQVPRTPqEWZVeFJNLYWmR62S60EGFpFFUFNejjL5QoWtvKdiWEvL/9viXEC42VHnlTQ409WbjVsZNtjVi5CX4u9ycWXyG/KiSaCR/SwbLRNkshrZzYqLqMFNUL5WXeTmhJxoZ75mtjJiZeUtvflMs1Rkgad/YzvCqEbDtYfbL5qIUUibWKy6SxpbWbhVI9jlwFhlA01R40j22x2l7Ll+SeUP9y9cu8pP9RydhaC9nZ8qRnHfvbuRl/BJNzbeVPY2Il7SwhAfq36Z96l4Ukwt52kHCWUkjdsViGetQ5KO67CohDd0p/lfV7fQh3tGohU9eEW4jnHQamBNIHuzYGYnFgb5I4ueU/Gc0LBQeZr06vSS9EuDSe/cijKAsOJuQ3dcBjgzJhN3Qo3fEdWXYmaNBM9bbvnvy+Eh6z0jYOhXxl4XPPeuFcLya8J3MXUytW3K7F3Kdv5BphgwnX/6CR8h4le53Sv1xI8lwpSr51ju+vFPJcTZ1LU/ObMfL5QspnhGSqDNSp7lOFbO6NkWS8EMpqzepcc32qkECWHS4hgVPIeB0TEemxqbnEwPbvCakuJcS/LGStindRc611iRIO7H0o2HeDzF5cFnLsFZhiUTE6h6T6wAp1iZWZUvBpIWXflER9ZJeQwfYt7qm1GpNCMnNb9PUFS+M/KmRwEu2UMpeQXV/1xrHo4uvD/Gy0uS3S/SPNesfzQzEi3CU4HzxJq766S4jYrajrWWNP3/jVzoShujYJdf52z99M+LtJLtLl9jEh4vZaVzvGSsYWm1rvkEuI2IFZGzGx+HCc3REiq05tIpyrUf7WReaY+4V4waC4r7xLQpb1YPFufAFvR0Oua5GJ33tFWXsPCkk2Lh1uIV7ZdtcWu7Ebwh9Dmv6AcKJ4v9IvW37YaK30qtI9KvQAPXxcqFOXh6DQhh0P6sJR8vWu6zirzBa2m/H3dQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHw7/gC8nUdvKSBArgAAAABJRU5ErkJggg=='
            ];
            $product->save();
        }
    }

    /**
     * @return mixed|null
     * @throws \Exception
     */
    public function getLicenseId($profile = null)
    {
        if(!$profile) {
            $profile = $this->getProfile();
        }

        if (!isset($profile->options['license_id'])) {
            $this->authenticate($profile);
            $response = $this->httpRequest('https://xenforo.com/customers/');

            $dom = new Dom();
            $dom->load($response->getBody()->getContents());
            /** @var Dom\Collection $licenses */
            $licenses = $dom->find('.license');

            if (!count($licenses)) {
                $this->exception(\XF::phrase('th_iau_no_xf_licenses_found_for_account_x',
                    ['account' => $profile->options['email']]));
            }

            $license = false;
            foreach ($licenses as $potentialLicense) {
                /** @var Dom\HtmlNode $license */
                $licenseUrl = $potentialLicense->find('.licenseDetails .title a')->getAttribute('href');

                if ($licenseUrl == $profile->options['license_url']) {
                    $license = $potentialLicense;
                    break;
                }
            }

            if (!$license) {
                $this->exception(\XF::phrase('th_iau_no_xf_license_found_for_url_x',
                    ['url' => $profile->options['license_url']]));
            }

            $url = $license->find('.downloadLink a')->getAttribute('href');
            preg_match('/l=([^&]*)/', $url, $matches);

            if ($matches[1]) {
                $options = $profile->options;
                $options['license_id'] = $matches[1];
                $profile->options = $options;
                if ($profile && $profile->profile_id) {
                    $profile->fastUpdate('options', $options);
                }
            }
        }

        if (!isset($profile->options['license_id'])) {
            $this->exception(\XF::phrase('th_iau_xf_license_id_could_not_be_found'));
        }

        return $profile->options['license_id'];
    }

    /**
     * @throws \Exception
     */
    public function checkForUpdates()
    {
        if (!$this->getProfile()) {
            return;
        }

        $addOns = $this->finder('XF:AddOn')
            ->where('addon_id', '=', array_keys($this->addOnMap))
            ->with('THInstallUpgradeData')
            ->fetch();

        foreach ($addOns as $addOn) {
            $this->checkAddOnForUpdates($addOn->getRelationOrDefault('THInstallUpgradeData'));
        }
    }

    /**
     * @param $productId
     * @param string $getter
     * @return array|mixed
     * @throws \Exception
     */
    protected function checkProductForUpdate($productId, $getter = 'string')
    {
        $products = $this->loadProductsJson();
        foreach ($products as $addOnId => $product) {
            if ($product['internal_id'] === $productId) {
                switch($getter) {
                    case 'id':
                        return $product['versionId'];
                    case 'string':
                        return $product['version'];
                    default:
                        return [
                            'id' => $product['versionId'],
                            'string' => $product['version'],
                        ];
                }
            }
        }

        return false;
    }

    /**
     * @param AddOn $addOn
     * @return string
     * @throws \Exception
     */
    public function downloadAddOn(AddOn $addOn)
    {
        $this->authenticate($this->getProfile());
        $licenseId = $this->getLicenseId();

        $version = $this->checkProductForUpdate($this->addOnMap[$addOn->addon_id], 'id');

        $response = $this->httpRequest('https://xenforo.com/customers/download', [
            'body' => [
                'l' => $licenseId,
                'd' => $this->addOnMap[$addOn->addon_id],
                'agree' => 1,
                'options' => [
                    'upgradePackage' => 1
                ],
                'download_version_id' => $version
            ]
        ], 'post');

        $tempFile = File::getTempFile();
        File::writeFile($tempFile, $response->getBody());
        $abstractPath = sprintf('internal-data://install-upgrade/addons/xf-addon-%s-%s.zip',
            $addOn->addon_id, \XF::$time);
        File::copyFileToAbstractedPath($tempFile, $abstractPath);

        return $abstractPath;
    }

    /**
     * @param AddOn $addOn
     * @throws \Exception
     */
    public function checkAddOnForUpdates(AddOn $addOn)
    {
//        $this->authenticate($this->getProfile());
        $addOn->latest_version = $this->checkProductForUpdate($this->addOnMap[$addOn->addon_id]);
        $addOn->update_available = $this->compareVersions($addOn->AddOn->version_string, $addOn->latest_version);
        $addOn->saveIfChanged();
    }

    /**
     * @param Language $language
     * @return bool
     */
    public function checkLanguageForUpdates(Language $language)
    {
        return true;
    }

    /**
     * @param Style $style
     * @return bool
     */
    public function checkStyleForUpdates(Style $style)
    {
        return true;
    }

    /**
     * @param Language $language
     * @return bool
     */
    public function downloadLanguage(Language $language)
    {
        return true;
    }

    /**
     * @param Style $style
     * @return bool
     */
    public function downloadStyle(Style $style)
    {
        return true;
    }

    protected function loadProductsJson()
    {
        if (!$this->products) {
            $response = $this->httpRequest('https://installandupgradeapi.themehouse.com/latestVersions.json');
            $body = $response->getBody()->getContents();
            $this->products = json_decode($body, true);
        }

        return $this->products;
    }

    /**
     * @param Product $product
     * @return string
     * @throws \Exception
     */
    protected function _downloadProduct(Product $product)
    {
        $this->authenticate($product->Profile);
        $licenseId = $this->getLicenseId();

        $version = $this->checkProductForUpdate($product->product_id, 'id');

        $response = $this->httpRequest('https://xenforo.com/customers/download', [
            'body' => [
                'l' => $licenseId,
                'd' => $product->product_id,
                'agree' => 1,
                'options' => [
                    'upgradePackage' => 1
                ],
                'download_version_id' => $version
            ],
        ], 'post');

        $tempFile = File::getTempFile();
        File::writeFile($tempFile, $response->getBody());
        $abstractPath = sprintf('internal-data://install-upgrade/addons/xf-%s-%s-%s.zip',
            $product->product_type, $product->product_id, \XF::$time);
        File::copyFileToAbstractedPath($tempFile, $abstractPath);

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
     */
    public function createProductFromUrl($url, $type)
    {
        // TODO: Implement createProductFromUrl() method.
    }
}