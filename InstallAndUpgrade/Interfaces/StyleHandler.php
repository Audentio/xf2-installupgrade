<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use XF\Mvc\Entity\ArrayCollection;

interface StyleHandler
{
    public function installStyleProducts(ProductBatch $productBatch);

    public function downloadStyleProduct(Product $style);

    public function checkStyleProductForUpdates(Product $style);

    public function createStyleProductFromUrl($url);

    public function isValidStyleUrl($url, &$error);
}