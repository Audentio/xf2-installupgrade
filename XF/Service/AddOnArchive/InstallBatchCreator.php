<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\AddOnArchive;

class InstallBatchCreator extends XFCP_InstallBatchCreator
{
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

        /** @noinspection PhpUndefinedMethodInspection */
        $this->thLastAddedAddOnId = $this->installBatch->thLastAddedAddOnId();

        return $response;
    }
}
