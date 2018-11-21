<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\Entity\Style;
use XF\Job\AbstractJob;

class UpgradeStyle extends AbstractJob
{
    /**
     * @param $maxRunTime
     * @return \XF\Job\JobResult
     * @throws \Exception
     */
    public function run($maxRunTime)
    {
        $data = $this->getData();
        /** @var Style $style */
        $style = \XF::em()->find('ThemeHouse\InstallAndUpgrade:Style', $data['style_id']);
        $style->handler->upgradeStyle($style);
        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('upgrading');
        $typePhrase = \XF::phrase('style');
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