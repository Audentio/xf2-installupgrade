<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use XF\Mvc\Entity\Repository;

class Log extends Repository
{
    public function clearLog()
    {
        $this->db()->emptyTable('xf_th_installupgrade_log');
    }
}