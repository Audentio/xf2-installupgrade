<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XFApi\Dto\DBTech\eCommerce\ProductDto;

/**
 * Class AtelierAphelion
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade
 */
class AtelierAphelion extends DragonByte
{
    /**
     * @var string
     */
    public $apiUrl = 'https://atelieraphelion.com/api';
    /**
     * @var string
     */
    public $apiKeyUrl = 'https://atelieraphelion.com/dbtech-ecommerce/account/api-key';
    /**
     * @var string
     */
    protected $licencesUrl = 'https://atelieraphelion.com/dbtech-ecommerce/licenses/';

    /**
     * @var string
     */
    protected $exceptionPrefix = '[Atelier Aphelion Install & Upgrade]';

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.atelieraphelion');
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
        return parent::createProductFromProductDto($product, $addOn, false);
    }

    /**
     * @return array
     */
    protected function getContext()
    {
        // Rather than a bunch of if statements, we'll just construct the product filter this way
        $version = explode('.', \XF::$version);

        return [
            'platforms' => ['xf' . $version[0], 'xf' . $version[0] . $version[1]],
            'type' => 'full', // We don't support demo downloads
            'categoryIds' => null,
            //'categoryIds' => strpos($this->apiUrl, 'http://localhost') !== false ? [1, 2] : [5],
        ];
    }
}