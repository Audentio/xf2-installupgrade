<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\XF\Entity\Style;
use XF;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\PrintableException;

/**
 * Class ImportTHStyles
 * @package ThemeHouse\InstallAndUpgrade\Job
 */
class ImportTHStyles extends AbstractJob
{

    /**
     * @param $maxRunTime
     * @return JobResult
     * @throws PrintableException
     */
    public function run($maxRunTime)
    {
        if (!isset($this->app->get('addon.cache')['ThemeHouse/UIX'])) {
            return $this->complete();
        }

        $db = XF::db();
        $db->beginTransaction();
        $styles = $this->app->em()->getFinder('XF:Style')->fetch();
        foreach ($styles as $style) {
            /** @var \ThemeHouse\UIX\XF\Entity\Style $style */
            if (!$style->th_product_id_uix) {
                continue;
            }

            /** @var Style $product */
            $product = $style->getRelationOrDefault('THInstallUpgradeData');
            $product->bulkSet([
                'style_id' => $style->style_id,
                'download_url' => 'https://themehouse.com',
                'current_version' => $style->th_product_version_uix,
                'extra' => [
                    'product_id' => $style->th_product_id_uix
                ]
            ]);
            $product->save();
        }
        $db->commit();

        return $this->complete();
    }

    /**
     * @return string
     */
    public function getStatusMessage()
    {
        $actionPhrase = XF::phrase('th_iau_finishing_installation');
        return sprintf('%s... (%s)', $actionPhrase,
            XF::language()->numberFormat(1)
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