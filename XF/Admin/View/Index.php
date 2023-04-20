<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View;

use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF;
use XF\PrintableException;

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
     * @throws PrintableException
     */
    public function renderHtml()
    {
        if (is_callable(parent::class . '::renderHtml')) {
            /** @noinspection PhpUndefinedMethodInspection */
            parent::renderHtml();
        }

        /** @var InstallAndUpgrade $installUpgradeRepo */
        $installUpgradeRepo = XF::repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
        $this->params['th_iau_upgrades'] = $installUpgradeRepo->getIndexUpdateInfo();
    }
}
