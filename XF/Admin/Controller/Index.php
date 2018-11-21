<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Reply\View;

class Index extends XFCP_Index
{
    /**
     * @return View
     */
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            /** @var InstallAndUpgrade $repository */
            $repository = $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');

            $visitor = \XF::visitor();

            $response->setParam('thIAUXenForo', $repository->getAvailableXenForoUpgrade());

            if ($visitor->hasAdminPermission('addOn')) {
                $response->setParam('thIAUAddOns', $repository->getAvailableAddOnUpgrades()->fetch());
            }

            if ($visitor->hasAdminPermission('style')) {
                $response->setParam('thIAUStyles', $repository->getAvailableStyleUpgrades()->fetch());
            }

            if ($visitor->hasAdminPermission('language')) {
                $response->setParam('thIAULanguages', $repository->getAvailableLanguageUpgrades()->fetch());
            }
        }

        return $response;
    }
}