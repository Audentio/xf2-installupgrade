<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View;

class Index extends XFCP_Index
{
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml')) {
            parent::renderHtml();
        }

        $this->params['th_iau_upgrades'] = \XF::repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade')
            ->getIndexUpdateInfo();
    }
}
