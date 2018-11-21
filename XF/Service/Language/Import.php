<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\Language;

use ThemeHouse\InstallAndUpgrade\Entity\Language;

class Import extends XFCP_Import
{
    /**
     * @param \SimpleXMLElement $document
     * @return \XF\Mvc\Entity\Entity
     * @throws \XF\PrintableException
     */
    protected function getTargetLanguage(\SimpleXMLElement $document)
    {
        $language = parent::getTargetLanguage($document);

        /** @var Language $installUpgradeData */
        $installUpgradeData = $language->getRelationOrDefault('THInstallUpgradeData');

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
            'content_type' => 'language',
            'content_id' => $language->language_id,
            'timestamp' => \XF::$time,
            'action' => $this->overwriteLanguage ? 'upgrade' : 'install'
        ]);

        $logEntry->save();

        return $language;
    }
}