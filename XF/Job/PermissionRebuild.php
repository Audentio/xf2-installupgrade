<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Job;

/**
 * Extends \XF\Job\PermissionRebuild
 */
class PermissionRebuild extends XFCP_PermissionRebuild
{
    public function run($maxRunTime)
    {
        $registry = \XF::app()->registry();
        if ($registry->get('svBulkJob'))
        {
            $registry->set('svBulkJob.permRebuild', 1);
            return $this->complete();
        }

        return parent::run($maxRunTime);
    }
}