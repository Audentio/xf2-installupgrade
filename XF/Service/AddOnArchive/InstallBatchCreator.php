<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\AddOnArchive;

use ThemeHouse\InstallAndUpgrade\XF\Entity\AddOnInstallBatch;

/**
 * Class InstallBatchCreator
 * @package ThemeHouse\InstallAndUpgrade\XF\Service\AddOnArchive
 *
 * @property AddOnInstallBatch installBatch
 */
class InstallBatchCreator extends XFCP_InstallBatchCreator
{
    /**
     * @var
     */
    protected $thLastAddedAddOnId;

    /**
     * @return mixed
     */
    public function thLastAddedAddOnId()
    {
        return $this->thLastAddedAddOnId;
    }

    /**
     * @param $tempFile
     * @param $error
     * @return bool
     */
    protected function setupAddOnZip($tempFile, &$error)
    {
        $response = parent::setupAddOnZip($tempFile, $error);

        $this->thLastAddedAddOnId = $this->installBatch->thLastAddedAddOnId();

        return $response;
    }
}
