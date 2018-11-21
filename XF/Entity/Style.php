<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Entity;

use XF\Util\File;
use XF\Util\Hash;
use XF\Util\Json;

class Style extends XFCP_Style
{
    public function isInstallUpgradeJsonHashChanged()
    {
        $designerModePath = \XF::app()->designerOutput()->getDesignerModePath($this->designer_mode);
        $jsonPath = $designerModePath . DIRECTORY_SEPARATOR . 'install-upgrade.json';

        if (!file_exists($jsonPath)) {
            return false;
        }

        $currentHash = Hash::hashTextFile($jsonPath, 'sha256');
        $oldHash = $this->getRelationOrDefault('THInstallUpgradeData')->json_hash;
        return $currentHash !== $oldHash;
    }

    /**
     * @throws \XF\PrintableException
     */
    public function importInstallUpgradeJSON()
    {
        if(!$this->designer_mode) {
            return;
        }

        /** @var Style $data */
        $data = $this->getRelationOrDefault('THInstallUpgradeData');

        $designerModePath = \XF::app()->designerOutput()->getDesignerModePath($this->designer_mode);
        $jsonPath = $designerModePath . DIRECTORY_SEPARATOR . 'install-upgrade.json';

        if (!file_exists($jsonPath)) {
            return;
        }

        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $data->bulkSet($jsonData);
        $data->json_hash = Hash::hashTextFile($jsonPath, 'sha256');
        $data->save();
    }

    public function exportInstallUpgradeJSON()
    {
        if(!$this->designer_mode) {
            return;
        }

        $designerModePath = \XF::app()->designerOutput()->getDesignerModePath($this->designer_mode);
        $jsonPath = $designerModePath . DIRECTORY_SEPARATOR . 'install-upgrade.json';


        if (!file_exists($jsonPath)) {
            /** @var Style $data */
            $data = $this->getRelationOrDefault('THInstallUpgradeData');
            $fileData = [
                'download_url' => $data->download_url,
                'current_version' => $data->current_version,
                'extra' => $data->extra
            ];

            File::writeFile($jsonPath, Json::jsonEncodePretty($fileData), false);
            $data->json_hash = Hash::hashTextFile($jsonPath, 'sha256');
            $data->save();
        }
    }
}