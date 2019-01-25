<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\AddOnArchive;

class InstallBatchCreator extends XFCP_InstallBatchCreator
{
    protected $thLastAddedAddOnId;

    public function thLastAddedAddOnId()
    {
        return $this->thLastAddedAddOnId;
    }

    protected function setupAddOnZip($tempFile, &$error)
    {
        $response = parent::setupAddOnZip($tempFile, $error);

        /** @noinspection PhpUndefinedMethodInspection */
        $this->thLastAddedAddOnId = $this->installBatch->thLastAddedAddOnId();

        return $response;
    }
}
