<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;

/**
 * Interface LanguageHandler
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces
 */
interface LanguageHandler
{
    /**
     * @param ProductBatch $productBatch
     * @return mixed
     */
    public function installLanguageProducts(ProductBatch $productBatch);

    /**
     * @param Product $language
     * @return mixed
     */
    public function downloadLanguageProduct(Product $language);

    /**
     * @param Product $language
     * @return mixed
     */
    public function checkLanguageProductForUpdates(Product $language);

    /**
     * @param $url
     * @param null $error
     * @return mixed
     */
    public function createLanguageProductFromUrl($url, &$error = null);

    /**
     * @param $url
     * @param $error
     * @return mixed
     */
    public function isValidLanguageUrl($url, &$error);
}