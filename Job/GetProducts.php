<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use Exception;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\ProductList;
use XF;
use XF\Job\AbstractJob;
use XF\Job\JobResult;

/**
 * Class GetProducts
 * @package ThemeHouse\InstallAndUpgrade\Job
 */
class GetProducts extends AbstractJob
{
    /**
     * @var array
     */
    protected $defaultData = [
        'steps' => 1,
        'profile_id' => 0
    ];

    /**
     * @param int $maxRunTime
     * @return JobResult
     * @throws Exception
     */
    public function run($maxRunTime)
    {
        $start = microtime(true);

        $this->data['steps']++;

        $db = $this->app->db();
        $em = $this->app->em();

        $profileIds = $db->fetchAllColumn($db->limit(
            "
                SELECT profile_id
                FROM xf_th_installupgrade_profile
                WHERE profile_id > ?
                ORDER BY profile_id
            ", 100
        ), $this->data['profile_id']);

        if (!$profileIds) {
            return $this->complete();
        }

        foreach ($profileIds as $profileId) {
            if (microtime(true) - $start >= $maxRunTime) {
                break;
            }

            $this->data['profile_id'] = $profileId;

            /** @var Profile $profile */
            $profile = $em->find('ThemeHouse\InstallAndUpgrade:Profile', $profileId);
            $handler = $profile->getHandler();

            if (!$handler || !$handler->getCapability('productList')) {
                continue;
            }

            /** @var ProductList $handler */
            $handler->getProducts();
        }

        return $this->resume();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = XF::phrase('th_installupgrade_fetching_products');
        return sprintf('%s... (%s)', $actionPhrase,
            XF::language()->numberFormat($this->data['steps'])
        );
    }

    /**
     * @return bool
     */
    public function canCancel()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return true;
    }
}