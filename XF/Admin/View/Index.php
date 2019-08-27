<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View;

use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;

/**
 * Class Index
 *
 * @package ThemeHouse\InstallAndUpgrade\XF\Admin\View
 *
 * @property array $params
 */
class Index extends XFCP_Index
{
    /**
     * @throws \XF\PrintableException
     */
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml')) {
            parent::renderHtml();
        }

        /** @var InstallAndUpgrade $installUpgradeRepo */
        $installUpgradeRepo = \XF::repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
        $this->params['th_iau_upgrades'] = $installUpgradeRepo->getIndexUpdateInfo();
    }
}
