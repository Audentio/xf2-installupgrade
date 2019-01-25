<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\LanguageHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits\LanguageHandlerTrait;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

class UpdateCheck extends AbstractJob
{

    protected $defaultData = [
        'profileId' => 0,
        'productId' => 0,
        'steps' => 0
    ];

    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     * @throws \Exception
     */
    public function run($maxRunTime)
    {
        $start = microtime(true);

        $db = $this->app->db();
        $em = $this->app->em();

        $productIds = $db->fetchAllColumn($db->limit(
            "
            SELECT product_id
            FROM xf_th_installupgrade_product
            WHERE profile_id = ? AND product_id > ?
            ORDER BY product_id
        ", 100
        ), [$this->data['profileId'], $this->data['productId']]);

        if (empty($productIds)) {
            $this->data['productId'] = 0;

            $nextProfile = $db->fetchOne("
                SELECT profile_id
                FROM xf_th_installupgrade_profile
                WHERE profile_id > ?
                ORDER BY profile_id
            ", $this->data['profileId']);

            if ($nextProfile) {
                $this->data['profileId'] = $nextProfile;
                return $this->resume();
            } else {
                return $this->complete();
            }
        }

        foreach ($productIds as $productId) {
            if (microtime(true) - $start >= $maxRunTime) {
                break;
            }

            $this->data['steps']++;
            $this->data['productId'] = $productId;

            /** @var Product $product */
            $product = $em->find('ThemeHouse\InstallAndUpgrade:Product', [$this->data['profileId'], $productId]);

            $profile = $product->Profile;

            if (!$profile) {
                continue;
            }

            $handler = $profile->getHandler();

            if (!$handler) {
                continue;
            }

            switch ($product->product_type) {
                case 'addOn':
                    /** @var AddOnHandler $handler */
                    $handler->checkAddOnProductForUpdates($product);
                    break;

                case 'style':
                    /** @var StyleHandler $handler */
                    $handler->checkStyleProductForUpdates($product);
                    break;

                case 'language':
                    /** @var LanguageHandler $handler */
                    $handler->checkLanguageProductForUpdates($product);
            }
        }

        return $this->resume();
    }

    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('th_installupgrade_checking_for_updates');
        return sprintf('%s... (%s)', $actionPhrase, $this->data['steps']);
    }

    public function canCancel()
    {
        return true;
    }

    public function canTriggerByChoice()
    {
        return false;
    }
}