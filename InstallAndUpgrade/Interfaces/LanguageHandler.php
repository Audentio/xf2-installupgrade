<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;

interface LanguageHandler
{
    public function installLanguageProducts(ProductBatch $productBatch);

    public function downloadLanguageProduct(Product $language);

    public function checkLanguageProductForUpdates(Product $language);

    public function createLanguageProductFromUrl($url);

    public function isValidLanguageUrl($url, &$error);
}