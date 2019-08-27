<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Repository;

/**
 * Class Style
 * @package ThemeHouse\InstallAndUpgrade\XF\Repository
 */
class Style extends XFCP_Style
{
    /**
     *
     */
    public function updateAllStylesLastModifiedDate()
    {
        $registry = \XF::app()->registry();
        if ($registry->get('svBulkJob')) {
            $registry->set('svBulkJob.styleRebuild', 1);

            $this->db()->update('xf_style', ['last_modified_date' => time()], null);
            \XF::registry()->set('masterStyleModifiedDate', time());
            return;
        }

        parent::updateAllStylesLastModifiedDate();
    }
}