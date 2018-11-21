<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\Entity\Language;
use XF\Job\AbstractJob;

class UpgradeLanguage extends AbstractJob
{
    /**
     * @param $maxRunTime
     * @return \XF\Job\JobResult
     * @throws \Exception
     */
    public function run($maxRunTime)
    {
        $data = $this->getData();
        /** @var Language $language */
        $language = \XF::em()->find('ThemeHouse\InstallAndUpgrade:Language', $data['language_id']);
        $language->handler->upgradeLanguage($language);
        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = \XF::phrase('upgrading');
        $typePhrase = \XF::phrase('language');
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