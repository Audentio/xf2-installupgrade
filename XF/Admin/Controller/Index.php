<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Reply\View;
use XF\PrintableException;

/**
 * Class Index
 * @package ThemeHouse\InstallAndUpgrade\XF\Admin\Controller
 */
class Index extends XFCP_Index
{
    /**
     * @return View
     * @throws PrintableException
     */
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            /** @var InstallAndUpgrade $iauRepo */
            $iauRepo = $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');

            $response->setParam('th_iau_upgrades',
                $iauRepo->getIndexUpdateInfo());
        }

        return $response;
    }
}
