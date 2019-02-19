<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;

interface AddOnHandler
{
    public function installAddOnProduct(Product $addOn);

    public function installAddOnProducts(ProductBatch $productBatch);

    public function downloadAddOnProduct(Product $addOn);

    public function checkAddOnProductForUpdates(Product $addOn);

    public function createAddOnProductFromUrl($url);

    public function isValidAddOnUrl($url, &$error);
}