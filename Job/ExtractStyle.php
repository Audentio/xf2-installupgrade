<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\Timer;

/**
 * Class ExtractStyle
 * @package ThemeHouse\InstallAndUpgrade\Job
 */
class ExtractStyle extends AbstractJob
{
    /**
     * @var array
     */
    protected $defaultData = [
        'batchId' => 0,
        'productId' => 0,
        'profileId' => 0,
        'start' => 0
    ];

    /**
     * @param int $maxRunTime
     *
     * @return JobResult
     */
    public function run($maxRunTime)
    {
        $em = $this->app->em();

        /** @var ProductBatch $batch */
        $batch = $em->find('ThemeHouse\InstallAndUpgrade:ProductBatch', $this->data['batchId']);

        if (!$batch) {
            $this->complete();
        }

        $product = $batch->getProducts()->first();

        $zipFile = $batch->getFile($product);

        /** @var \XF\Service\AddOnArchive\Extractor $extractor */
        $extractor = $this->app->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $zipFile);
        $timer = new Timer($maxRunTime);
        $result = $extractor->copyFiles([], $this->data['start'], $timer);

        switch ($result['status']) {
            case 'error':
                return $this->complete();
                break;

            case 'incomplete':
                $this->data['start'] = $result['last'] + 1;
                break;

            case 'complete':
                return $this->complete();
                break;

            default:
                throw new \LogicException("Unknown result from copy '$result[status]'");
        }

        return $this->resume();
    }

    /**
     * @return \XF\Phrase
     */
    public function getStatusMessage()
    {
        return \XF::phrase('copying_files...');
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