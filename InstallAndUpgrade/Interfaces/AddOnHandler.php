<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;

/**
 * Interface AddOnHandler
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces
 */
interface AddOnHandler
{
    /**
     * @param Product $addOn
     * @return mixed
     */
    public function installAddOnProduct(Product $addOn);

    /**
     * @param ProductBatch $productBatch
     * @return mixed
     */
    public function installAddOnProducts(ProductBatch $productBatch);

    /**
     * @param Product $addOn
     * @return mixed
     */
    public function downloadAddOnProduct(Product $addOn);

    /**
     * @param Product $addOn
     * @return mixed
     */
    public function checkAddOnProductForUpdates(Product $addOn);

    /**
     * @param $url
     * @param null $error
     * @return mixed
     */
    public function createAddOnProductFromUrl($url, &$error = null);

    /**
     * @param $url
     * @param $error
     * @return mixed
     */
    public function isValidAddOnUrl($url, &$error);
}