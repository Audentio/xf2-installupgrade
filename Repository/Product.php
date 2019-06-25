<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

/**
 * Class Product
 * @package ThemeHouse\InstallAndUpgrade\Repository
 */
class Product extends Repository
{
    /**
     * @param $profiles
     * @param string $type
     * @return \XF\Mvc\Entity\Finder
     */
    public function findProductListProductsForProfiles(ArrayCollection $profiles, $type = 'addOn')
    {
        $products = $this->findProducts()
            ->where('product_type', '=', $type)
            ->where('profile_id', '=', $profiles->keys())
            ->order(['installed', 'title'], 'ASC');

        return $products;
    }

    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function findProducts()
    {
        return $this->finder('ThemeHouse\InstallAndUpgrade:Product');
    }
}