<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use XF\Job\AbstractJob;

class PostInstall extends AbstractJob
{

    /**
     * @param $maxRunTime
     * @return \XF\Job\JobResult
     * @throws \XF\PrintableException
     */
    public function run($maxRunTime)
    {
        $db = \XF::db();
        $db->beginTransaction();
        $addOns = $this->app->em()->getFinder('XF:AddOn')->fetch();
        foreach ($addOns as $addOn) {
            /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $addOn */
            $addOn->importInstallUpgradeJSON();
        }
        $db->commit();

        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('th_iau_finishing_installation');
        return sprintf('%s... (%s)', $actionPhrase,
            \XF::language()->numberFormat(1)
        );
    }

    /**
     * @return bool
     */
    public function canCancel()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function canTriggerByChoice()
    {
        return false;
    }
}