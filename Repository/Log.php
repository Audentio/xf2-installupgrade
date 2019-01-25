<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF\Entity\User;
use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class Log extends Repository
{
    /**
     * @param Product $product
     * @param $action
     * @param User|null $user
     * @throws \XF\PrintableException
     */
    public function log(Product $product, $action, $extra = [], User $user = null)
    {
        if (!$user) {
            $user = \XF::visitor();
        }

        $log = $this->em->create('ThemeHouse\InstallAndUpgrade:Log');
        $log->bulkSet([
            'profile_id' => $product->profile_id,
            'product_id' => $product->product_id,
            'user_id' => $user->user_id,
            'action' => $action,
            'content_type' => $product->product_type,
            'content_id' => $product->content_id,
            'extra' => $extra
        ]);
        $log->save();
    }

    /**
     * @return Finder
     */
    public function findLogs()
    {
        return $this->finder('ThemeHouse\InstallAndUpgrade:Log')
            ->setDefaultOrder('log_date', 'DESC')
            ->with('User')
            ->with('Profile')
            ->with('Product');
    }
}