<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Patch1010030
{
    public function upgrade1010030Step1()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_log');
    }

    public function upgrade1010030Step2()
    {
        $this->installStep1();
    }

    public function upgrade1010030Step3()
    {
        $this->installStep2();
    }
}