<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Job;

use XF;
use XF\Job\JobResult;

/**
 * Class PermissionRebuild
 * @package ThemeHouse\InstallAndUpgrade\XF\Job
 */
class PermissionRebuild extends XFCP_PermissionRebuild
{
    /**
     * @param $maxRunTime
     * @return JobResult
     */
    public function run($maxRunTime)
    {
        $registry = XF::app()->registry();
        if ($registry->get('svBulkJob')) {
            $registry->set('svBulkJob.permRebuild', 1);
            return $this->complete();
        }

        return parent::run($maxRunTime);
    }
}