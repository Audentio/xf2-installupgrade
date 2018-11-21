<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\Style;

use ThemeHouse\InstallAndUpgrade\Entity\Style;

class Import extends XFCP_Import
{
    /**
     * @param \SimpleXMLElement $document
     * @return null|\XF\Entity\Style|\XF\Mvc\Entity\Entity
     * @throws \XF\PrintableException
     */
    protected function getTargetStyle(\SimpleXMLElement $document)
    {
        $style = parent::getTargetStyle($document);

        /** @var Style $installUpgradeData */
        $installUpgradeData = $style->getRelationOrDefault('THInstallUpgradeData');

        $installUpgradeData->bulkSet([
            'extra' => [
                'dev' => isset($document['dev']) ? (string)$document['dev'] : '',
                'dev_url' => isset($document['dev_url']) ? (string)$document['dev_url'] : '',
                'faq_url' => isset($document['faq_url']) ? (string)$document['faq_url'] : '',
                'support' => isset($document['support']) ? (string)$document['support'] : '',
            ],
            'current_version' => isset($document['current_version']) ? (string)$document['current_version'] : '',
            'download_url' => isset($document['download_url']) ? (string)$document['download_url'] : ''
        ]);

        $installUpgradeData->save(true, false);

        $logEntry = \XF::em()->create('ThemeHouse\InstallAndUpgrade:Log');

        $logEntry->bulkSet([
            'from_version' => '',
            'to_version' => $installUpgradeData->current_version,
            'user_id' => \XF::visitor()->user_id,
            'content_type' => 'style',
            'content_id' => $style->style_id,
            'timestamp' => \XF::$time,
            'action' => $this->overwriteStyle ? 'upgrade' : 'install'
        ]);

        $logEntry->save();

        return $style;
    }
}