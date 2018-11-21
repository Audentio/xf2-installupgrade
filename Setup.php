<?php

namespace ThemeHouse\InstallAndUpgrade;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_addon', function (Create $table) {
            $table->addColumn('addon_id', 'varbinary', 50)->primaryKey();
            $table->addColumn('download_url', 'text');
            $table->addColumn('auto_update', 'tinyint')->setDefault(0);
            $table->addColumn('update_check', 'tinyint')->setDefault(0);
            $table->addColumn('update_available', 'tinyint')->setDefault(0);
            $table->addColumn('latest_version', 'text');
            $table->addColumn('extra', 'blob')->nullable();
            $table->addColumn('json_hash', 'varbinary', 64)->setDefault('');
        });
    }

    public function installStep2()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_style', function (Create $table) {
            $table->addColumn('style_id', 'int')->primaryKey();
            $table->addColumn('download_url', 'text');
            $table->addColumn('auto_update', 'tinyint')->setDefault(0);
            $table->addColumn('update_check', 'tinyint')->setDefault(0);
            $table->addColumn('update_available', 'tinyint')->setDefault(0);
            $table->addColumn('current_version', 'text');
            $table->addColumn('latest_version', 'text');
            $table->addColumn('extra', 'blob')->nullable();
            $table->addColumn('json_hash', 'varbinary', 64)->setDefault('');
        });
    }

    public function installStep3()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_language', function (Create $table) {
            $table->addColumn('language_id', 'int')->primaryKey();
            $table->addColumn('download_url', 'text');
            $table->addColumn('auto_update', 'tinyint')->setDefault(0);
            $table->addColumn('update_check', 'tinyint')->setDefault(0);
            $table->addColumn('update_available', 'tinyint')->setDefault(0);
            $table->addColumn('current_version', 'text');
            $table->addColumn('latest_version', 'text');
            $table->addColumn('extra', 'blob')->nullable();
        });
    }

    public function installStep4()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_profile', function (Create $table) {
            $table->addColumn('profile_id', 'int')->autoIncrement();
            $table->addColumn('provider_id', 'varchar', 25);
            $table->addColumn('page_title', 'varchar', 100);
            $table->addColumn('base_url', 'varchar', 100)->setDefault('');
            $table->addColumn('has_tfa', 'bool')->setDefault(0);
            $table->addColumn('options', 'blob');
            $table->addColumn('active', 'bool')->setDefault(1);
            $table->addColumn('requires_decryption', 'bool')->setDefault(0);
        });
    }

    public function installStep5()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_provider', function (Create $table) {
            $table->addColumn('provider_id', 'varchar', 25)->primaryKey();
            $table->addColumn('provider_class', 'varchar', 100);
            $table->addColumn('addon_id', 'varbinary', 50)->setDefault('');
            $table->addColumn('is_unique', 'tinyint')->setDefault(0);
        });

        $this->db()->insertBulk('xf_th_installupgrade_provider', [
            [
                'provider_id' => 'xenforo',
                'provider_class' => 'ThemeHouse\InstallAndUpgrade:XenForo',
                'addon_id' => 'ThemeHouse/InstallAndUpgrade',
                'is_unique' => true
            ],
            [
                'provider_id' => 'themehouse',
                'provider_class' => 'ThemeHouse\InstallAndUpgrade:ThemeHouse',
                'addon_id' => 'ThemeHouse/InstallAndUpgrade',
                'is_unique' => true
            ],
            [
                'provider_id' => 'xf2rm',
                'provider_class' => 'ThemeHouse\InstallAndUpgrade:XF2RM',
                'addon_id' => 'ThemeHouse/InstallAndUpgrade',
                'is_unique' => false
            ]
        ]);
    }

    public function installStep6()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_log', function (Create $table) {
            $table->addColumn('log_id', 'int')->autoIncrement();
            $table->addColumn('content_type', 'enum')->values(['addOn', 'language', 'style']);
            $table->addColumn('action', 'enum')->values(['install', 'upgrade']);
            $table->addColumn('from_version', 'text');
            $table->addColumn('to_version', 'text');
            $table->addColumn('content_id', 'text');
            $table->addColumn('user_id', 'int')->setDefault(0);
            $table->addColumn('timestamp', 'int')->setDefault(0);
        });
    }

    public function installStep7()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_product', function (Create $table) {
            $table->addColumn('profile_id', 'int');
            $table->addColumn('product_id', 'varchar', 60);
            $table->addColumn('product_type', 'enum')->values(['addOn', 'style', 'language']);
            $table->addColumn('title', 'varchar', 100);
            $table->addColumn('description', 'text');
            $table->addColumn('version', 'text');
            $table->addColumn('extra', 'blob');
            $table->addPrimaryKey(['profile_id', 'product_id']);
        });
    }

    public function upgrade1000032Step1() {
        $this->schemaManager()->alterTable('xf_th_installupgrade_profile', function (Alter $table) {
            $table->addColumn('active', 'bool')->setDefault(1);
        });
    }

    public function upgrade1000036Step1()
    {
        $this->schemaManager()->alterTable('xf_th_installupgrade_profile', function(Alter $table) {
            $table->addColumn('requires_decryption', 'bool')->setDefault(0);
        });
    }

    public function upgrade1000040Step1() {
        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
    }

    public function postInstall(array &$stateChanges)
    {
        parent::postInstall($stateChanges);

        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:PostInstall');
        \XF::app()->jobManager()->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
    }

    public function uninstallStep1()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_addon');
    }

    public function uninstallStep2()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_style');
    }

    public function uninstallStep3()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_language');
    }

    public function uninstallStep4()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_profile');
    }

    public function uninstallStep5()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_provider');
    }

    public function uninstallStep6()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_log');
    }

    public function uninstallStep7()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_product');
    }
}
