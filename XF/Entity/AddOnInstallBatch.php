<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

class AddOnInstallBatch extends XFCP_AddOnInstallBatch
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
     * @param $addOnId
     * @param $title
     * @param $newVersionId
     * @param $newVersionString
     * @param $tempFile
     */
    public function addAddOn($addOnId, $title, $newVersionId, $newVersionString, $tempFile)
    {
        $this->thLastAddedAddOnId = $addOnId;

        parent::addAddOn($addOnId, $title, $newVersionId, $newVersionString,
            $tempFile);
    }
}
