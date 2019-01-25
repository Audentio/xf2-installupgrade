<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;

trait Patch1000040
{
    public function upgrade1000032Step1()
    {
        $this->schemaManager()->alterTable('xf_th_installupgrade_profile', function (Alter $table) {
            $table->addColumn('active', 'bool')->setDefault(1);
        });
    }

    public function upgrade1000036Step1()
    {
        $this->schemaManager()->alterTable('xf_th_installupgrade_profile', function (Alter $table) {
            $table->addColumn('requires_decryption', 'bool')->setDefault(0);
        });
    }

    public function upgrade1000040Step1()
    {
        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
    }
}