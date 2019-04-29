<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;

trait Patch1000040
{
    public function upgrade1000032Step1()
    {
        $this->installStep1();
    }

    public function upgrade1000040Step1()
    {
        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
    }
}