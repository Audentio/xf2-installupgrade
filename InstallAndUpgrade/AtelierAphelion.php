<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Mvc\Entity\Finder;

class AtelierAphelion extends DragonByte
{
    public $apiUrl = 'https://atelieraphelion.com/api';
    public $apiKeyUrl = 'https://atelieraphelion.com/dbtech-ecommerce/account/api-key';
    protected $licencesUrl = 'https://atelieraphelion.com/dbtech-ecommerce/licenses/';

    protected $exceptionPrefix = '[Atelier Aphelion Install & Upgrade]';

    /**
     * @return \XF\Phrase
     */
    public function getTitle()
    {
        return \XF::phrase('install_upgrade_provider.atelieraphelion');
    }

    /**
     * @param Product                                $product
     * @param \XFApi\Dto\DBTech\eCommerce\ProductDto $addOn
     * @param bool                                   $withThumbnail
     * @return Product
     */
    protected function createProductFromProductDto(Product $product, \XFApi\Dto\DBTech\eCommerce\ProductDto $addOn, $withThumbnail = true)
    {
        return parent::createProductFromProductDto($product, $addOn, false);
    }

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