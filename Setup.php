<?php

namespace ThemeHouse\InstallAndUpgrade;

use ThemeHouse\InstallAndUpgrade\Setup\Patch1000040;
use ThemeHouse\InstallAndUpgrade\Setup\Patch1010030;
use ThemeHouse\InstallAndUpgrade\Setup\Patch1010130;
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

    /** ---- INSTALL ---- */

    public function installStep1()
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
            $table->addColumn('last_error_messages', 'blob');
        });
    }

    public function installStep2()
    {
        $this->schemaManager()->createTable('xf_th_installupgrade_product', function (Create $table) {
            $table->addColumn('profile_id', 'int');
            $table->addColumn('product_id', 'varchar', 60);
            $table->addColumn('product_type', 'enum')->values(['addOn', 'style', 'language']);
            $table->addColumn('content_id', 'varchar', 60)->setDefault('');
            $table->addColumn('title', 'varchar', 100);
            $table->addColumn('description', 'text');
            $table->addColumn('current_version', 'text');
            $table->addColumn('latest_version', 'text');
            $table->addColumn('update_available', 'tinyint')->setDefault(0);
            $table->addColumn('extra', 'blob');
            $table->addColumn('installed', 'tinyint')->setDefault(0);
            $table->addColumn('json_hash', 'varchar', 64)->setDefault('');
            $table->addPrimaryKey(['profile_id', 'product_id']);
        });
    }

    public function installStep3()
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

    public function installStep4()
    {
        $this->schemaManager()->alterTable('xf_style', function (Alter $table) {
            $table->addColumn('th_iau_profile_id', 'int');
            $table->addColumn('th_iau_product_id', 'varchar', 200);
            $table->addColumn('th_iau_current_version', 'varchar', 200);
            $table->addColumn('th_iau_xml', 'varchar', 200);
        });
    }

    public function installStep5()
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

    public function postInstall(array &$stateChanges)
    {
        $jobManager = \XF::app()->jobManager();
        $jobManager->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
    }

    /** ---- UPGRADE ---- */

    /** 1.0.0 Beta 10 */
    use Patch1000040;
    
    /** 1.1.0 */
    use Patch1010030;
    
    /** 1.1.1 */
    use Patch1010130;

    /** ---- UNINSTALL ---- */

    public function uninstallStep1()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_profile');
    }

    public function uninstallStep2()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_product');
    }

    public function uninstallStep3()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_product_batch');
    }

    public function uninstallStep4()
    {
        $this->schemaManager()->alterTable('xf_style', function (Alter $table) {
            $table->dropColumns(['th_iau_product_id', 'th_iau_current_version', 'th_iau_profile_id', 'th_iau_xml']);
        });
    }

    public function uninstallStep5()
    {
        $this->schemaManager()->dropTable('xf_th_installupgrade_log');
    }
}
