<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Repository;

class Product extends Repository
{
    /**
     * @return \XF\Mvc\Entity\Finder
     */
    public function findProducts()
    {
        return $this->finder('ThemeHouse\InstallAndUpgrade:Product');
    }

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
}