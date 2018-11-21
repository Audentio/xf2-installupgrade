<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use XF\Util\File;
use XF\Util\Hash;
use XF\Util\Json;

/**
 * Class AddOn
 * @package ThemeHouse\InstallAndUpgrade\XF\Entity
 *
 * @property AddOn THInstallUpgradeData
 */
class AddOn extends XFCP_AddOn
{
    /**
     * @throws \XF\PrintableException
     */
    protected function _postSave()
    {
        parent::_postSave();

        if ($this->isInsert()) {
            $addOn = new \XF\AddOn\AddOn($this);
            $json = $addOn->getJsonPath();

            if (!file_exists($json)) {
                $filePath = $addOn->getAddOnDirectory() . DIRECTORY_SEPARATOR . 'install-upgrade.json';
                $fileData = [
                    'download_url' => '',
                    'extra' => []
                ];

                File::createDirectory($addOn->getAddOnDirectory(), false);
                File::writeFile($filePath, Json::jsonEncodePretty($fileData), false);

                /** @var AddOn $data */
                $data = $this->getRelationOrDefault('THInstallUpgradeData');
                $data->json_hash = Hash::hashTextFile($filePath, 'sha256');
                $data->save();
            }
        }
    }

    public function isInstallUpgradeJsonHashChanged()
    {
        $addOn = new \XF\AddOn\AddOn($this);
        $filePath = $addOn->getAddOnDirectory() . DIRECTORY_SEPARATOR . 'install-upgrade.json';

        if (!file_exists($filePath)) {
            return false;
        }

        $currentHash = Hash::hashTextFile($filePath, 'sha256');
        $oldHash = $this->getRelationOrDefault('THInstallUpgradeData')->json_hash;
        return $currentHash !== $oldHash;
    }

    /**
     * @throws \XF\PrintableException
     */
    public function importInstallUpgradeJSON()
    {
        /** @var AddOn $data */
        $data = $this->getRelationOrDefault('THInstallUpgradeData');

        $ds = DIRECTORY_SEPARATOR;

        if (strpos($this->addon_id, '/') !== false) {
            $id = str_replace('/', $ds, $this->addon_id);
        } else {
            $id = $this->addon_id;
        }

        $jsonPath = \XF::getAddOnDirectory() . $ds . $id . $ds . 'install-upgrade.json';

        if (!file_exists($jsonPath)) {
            return;
        }

        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $data->bulkSet($jsonData);
        $data->json_hash = Hash::hashTextFile($jsonPath, 'sha256');
        $data->save();
    }

    function getAddOnHandler()
    {
        return \XF::app()->addOnManager()->getById($this->addon_id);
    }
}