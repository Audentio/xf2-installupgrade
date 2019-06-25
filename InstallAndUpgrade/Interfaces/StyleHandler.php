<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;

/**
 * Interface StyleHandler
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces
 */
interface StyleHandler
{
    /**
     * @param ProductBatch $productBatch
     * @return mixed
     */
    public function installStyleProducts(ProductBatch $productBatch);

    /**
     * @param Product $style
     * @return mixed
     */
    public function downloadStyleProduct(Product $style);

    /**
     * @param Product $style
     * @return mixed
     */
    public function checkStyleProductForUpdates(Product $style);

    /**
     * @param $url
     * @return mixed
     */
    public function createStyleProductFromUrl($url);

    /**
     * @param $url
     * @param $error
     * @return mixed
     */
    public function isValidStyleUrl($url, &$error);
}