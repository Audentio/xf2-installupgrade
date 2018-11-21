<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\Language;

use ThemeHouse\InstallAndUpgrade\Entity\Language;

class Export extends XFCP_Export
{
    /**
     * @param \DOMDocument $document
     * @return \DOMElement
     */
    protected function getLanguageNode(\DOMDocument $document)
    {
        $languageNode = parent::getLanguageNode($document);

        /** @var Language $installUpgradeData */
        $installUpgradeData = $this->getLanguage()->getRelationOrDefault('THInstallUpgradeData');
        $extra = array_merge([
            'dev' => '',
            'dev_url' => '',
            'faq_url' => '',
            'support_url' => '',
            'current_version' => ''
        ], $installUpgradeData->extra);

        $languageNode->setAttribute('dev', $extra['dev']);
        $languageNode->setAttribute('dev_url', $extra['dev_url']);
        $languageNode->setAttribute('faq_url', $extra['faq_url']);
        $languageNode->setAttribute('support_url', $extra['support_url']);
        $languageNode->setAttribute('download_url', $installUpgradeData->download_url);
        $languageNode->setAttribute('current_version', $extra['current_version']);

        return $languageNode;
    }
}