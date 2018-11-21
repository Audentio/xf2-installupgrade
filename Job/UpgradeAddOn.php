<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use XF\Job\AbstractJob;

class UpgradeAddOn extends AbstractJob
{
    /**
     * @param $maxRunTime
     * @return \XF\Job\JobResult
     * @throws \Exception
     */
    public function run($maxRunTime)
    {
        $data = $this->getData();
        /** @var AddOn $addOn */
        $addOn = \XF::em()->find('ThemeHouse\InstallAndUpgrade:AddOn', $data['addOn_id']);
        $addOn->handler->upgradeAddOn($addOn);
        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('upgrading');
        $typePhrase = \XF::phrase('add_on');
        return sprintf('%s... %s (%s)', $actionPhrase, $typePhrase,
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
        return true;
    }
}