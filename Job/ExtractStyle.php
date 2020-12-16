<?php

namespace ThemeHouse\InstallAndUpgrade\Job;

use LogicException;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use XF;
use XF\Job\AbstractJob;
use XF\Job\JobResult;
use XF\Phrase;
use XF\Service\AddOnArchive\Extractor;
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

        /** @var Product $product */
        $product = $batch->getProducts()->first();

        $zipFile = $batch->getFile($product);

        /** @var Extractor $extractor */
        $extractor = $this->app->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $zipFile);
        $timer = new Timer($maxRunTime);
        $result = $extractor->copyFiles([], $this->data['start'], $timer);

        switch ($result['status']) {
            case 'error':
            case 'complete':
                return $this->complete();

            case 'incomplete':
                $this->data['start'] = $result['last'] + 1;
                break;

            default:
                throw new LogicException("Unknown result from copy '$result[status]'");
        }

        return $this->resume();
    }

    /**
     * @return Phrase
     */
    public function getStatusMessage()
    {
        return XF::phrase('copying_files...');
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