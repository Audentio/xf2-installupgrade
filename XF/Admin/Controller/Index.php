<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use XF\Mvc\Reply\View;

class Index extends XFCP_Index
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if($response instanceof View) {
            $response->setParam('th_iau_upgrades', $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade')->getIndexUpdateInfo());
        }

        return $response;
    }
}
