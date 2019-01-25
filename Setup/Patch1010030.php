<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Patch1010030
{
    public function upgrade1010030Step1()
    {
        $this->schemaManager()->alterTable('xf_style', function (Alter $table) {
            $table->addColumn('th_iau_profile_id', 'int');
            $table->addColumn('th_iau_product_id', 'varchar', 200);
            $table->addColumn('th_iau_current_version', 'varchar', 200);
            $table->addColumn('th_iau_xml', 'varchar', 200);
        });
    }

    public function upgrade1010030Step2()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_product_batch',
            function (Create $table) {
                $table->addColumn('batch_id', 'int')->nullable()->autoIncrement();
                $table->addColumn('start_date', 'int')->setDefault(0);
                $table->addColumn('complete_date', 'int')->setDefault(0);
                $table->addColumn('product_ids', 'blob');
                $table->addColumn('results', 'blob');
            });
    }

    public function upgrade1010030Step3()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_log');
    }

    public function upgrade1010030Step4()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_log', function (Create $table) {
            $table->addColumn('log_id', 'int')->autoIncrement();
            $table->addColumn('profile_id', 'int');
            $table->addColumn('product_id', 'varchar', 200)->setDefault('');
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('log_date', 'int')->setDefault(0);
            $table->addColumn('action', 'text');
            $table->addColumn('content_type', 'varchar', 100)->setDefault('');
            $table->addColumn('content_id', 'varchar', 200)->setDefault('');
            $table->addColumn('extra', 'blob')->nullable();
        });
    }
}