<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Finder;
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
     * @return Finder
     */
    public function findProductListProductsForProfiles(ArrayCollection $profiles, $type = 'addOn')
    {
        return $this->findProducts()
            ->where('product_type', '=', $type)
            ->where('profile_id', '=', $profiles->keys())
            ->order(['installed', 'title'], 'ASC');
    }

    /**
     * @return Finder
     */
    public function findProducts()
    {
        return $this->finder('ThemeHouse\InstallAndUpgrade:Product');
    }
}