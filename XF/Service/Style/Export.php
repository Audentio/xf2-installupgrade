<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Service\Style;

use ThemeHouse\InstallAndUpgrade\Entity\Style;

class Export extends XFCP_Export
{
    /**
     * @param \DOMDocument $document
     * @return \DOMElement
     */
    protected function getStyleNode(\DOMDocument $document)
    {
        $styleNode = parent::getStyleNode($document);

        /** @var Style $installUpgradeData */
        $installUpgradeData = $this->getStyle()->getRelationOrDefault('THInstallUpgradeData');
        $extra = array_merge([
            'dev' => '',
            'dev_url' => '',
            'faq_url' => '',
            'support_url' => '',
            'current_version' => ''
        ], $installUpgradeData->extra);

        $styleNode->setAttribute('dev', $extra['dev']);
        $styleNode->setAttribute('dev_url', $extra['dev_url']);
        $styleNode->setAttribute('faq_url', $extra['faq_url']);
        $styleNode->setAttribute('support_url', $extra['support_url']);
        $styleNode->setAttribute('download_url', $installUpgradeData->download_url);
        $styleNode->setAttribute('current_version', $extra['current_version']);

        return $styleNode;
    }
}