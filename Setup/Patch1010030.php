<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\SchemaManager;

/**
 * Trait Patch1010030
 * @package ThemeHouse\InstallAndUpgrade\Setup
 */
trait Patch1010030
{
    /**
     *
     */
    public function upgrade1010030Step1()
    {
        /** @var SchemaManager $schemaManager */
        $schemaManager = $this->schemaManager();
        $schemaManager->dropTable('xf_th_installupgrade_log');
    }

    /**
     *
     */
    public function upgrade1010030Step2()
    {
        $this->installStep1();
    }

    /**
     *
     */
    public function upgrade1010030Step3()
    {
        $this->installStep2();
    }
}