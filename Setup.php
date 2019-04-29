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
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /** ---- INSTALL ---- */

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->createTable($tableName, $callback);
            $sm->alterTable($tableName, $callback);
        }
    }

    public function installStep2()
    {
        $sm = $this->schemaManager();

        foreach ($this->getAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
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

    public function postUpgrade($previousVersion, array &$stateChanges)
    {
        if ($previousVersion <= 1000040)
        {
            $jobManager = \XF::app()->jobManager();
            $jobManager->enqueue('ThemeHouse\InstallAndUpgrade:ImportTHStyles');
        }
    }


    /** ---- UNINSTALL ---- */

    public function uninstallStep4()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $callback)
        {
            $sm->dropTable($tableName);
        }
    }

    /**
     * Reverts database schema changes - table alters
     */
    public function uninstallStep5()
    {
        $sm = $this->schemaManager();

        foreach ($this->getRemoveAlterTables() as $tableName => $callback)
        {
            if ($sm->tableExists($tableName))
            {
                $sm->alterTable($tableName, $callback);
            }
        }
    }

    protected function getTables()
    {
        $tables = [];

        $tables['xf_th_installupgrade_profile'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'profile_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table,'provider_id', 'varchar', 25);
            $this->addOrChangeColumn($table,'page_title', 'varchar', 100);
            $this->addOrChangeColumn($table,'base_url', 'varchar', 100)->setDefault('');
            $this->addOrChangeColumn($table,'has_tfa', 'bool')->setDefault(0);
            $this->addOrChangeColumn($table,'options', 'blob');
            $this->addOrChangeColumn($table,'active', 'bool')->setDefault(1);
            $this->addOrChangeColumn($table,'requires_decryption', 'bool')->setDefault(0);
            $this->addOrChangeColumn($table,'last_error_messages', 'blob');
        };

        $tables['xf_th_installupgrade_product'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'profile_id', 'int');
            $this->addOrChangeColumn($table,'product_id', 'varchar', 60);
            $this->addOrChangeColumn($table,'product_type', 'enum')->values(['addOn', 'style', 'language']);
            $this->addOrChangeColumn($table,'content_id', 'varchar', 60)->setDefault('');
            $this->addOrChangeColumn($table,'title', 'varchar', 100);
            $this->addOrChangeColumn($table,'description', 'text');
            $this->addOrChangeColumn($table,'current_version', 'text');
            $this->addOrChangeColumn($table,'latest_version', 'text');
            $this->addOrChangeColumn($table,'update_available', 'tinyint')->setDefault(0);
            $this->addOrChangeColumn($table,'extra', 'blob');
            $this->addOrChangeColumn($table,'installed', 'tinyint')->setDefault(0);
            $this->addOrChangeColumn($table,'json_hash', 'varchar', 64)->setDefault('');
            $table->addPrimaryKey(['profile_id', 'product_id']);
        };

        $tables['xf_th_installupgrade_product_batch'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'batch_id', 'int')->nullable()->autoIncrement();
            $this->addOrChangeColumn($table,'start_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'complete_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'product_ids', 'blob');
            $this->addOrChangeColumn($table,'results', 'blob');
        };

        $tables['xf_th_installupgrade_log'] = function ($table) {
            /** @var Create|Alter $table */
            $this->addOrChangeColumn($table,'log_id', 'int')->autoIncrement();
            $this->addOrChangeColumn($table,'profile_id', 'int');
            $this->addOrChangeColumn($table,'product_id', 'varchar', 200)->setDefault('');
            $this->addOrChangeColumn($table,'user_id', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'log_date', 'int')->setDefault(0);
            $this->addOrChangeColumn($table,'action', 'text');
            $this->addOrChangeColumn($table,'content_type', 'varchar', 100)->setDefault('');
            $this->addOrChangeColumn($table,'content_id', 'varchar', 200)->setDefault('');
            $this->addOrChangeColumn($table,'extra', 'blob')->nullable();
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getAlterTables()
    {
        $tables = [];

        $tables['xf_style'] = function (Alter $table) {
            $this->addOrChangeColumn($table, 'th_iau_profile_id', 'int');
            $this->addOrChangeColumn($table, 'th_iau_product_id', 'varchar', 200);
            $this->addOrChangeColumn($table, 'th_iau_current_version', 'varchar', 200);
            $this->addOrChangeColumn($table, 'th_iau_xml', 'varchar', 200);
        };

        return $tables;
    }

    /**
     * @return array
     */
    protected function getRemoveAlterTables()
    {
        $tables = [];

        $tables['xf_style'] = function (Alter $table) {
            $table->dropColumns(['th_iau_product_id', 'th_iau_current_version', 'th_iau_profile_id', 'th_iau_xml']);
        };

        return $tables;
    }

    /**
     * @param Create|Alter $table
     * @param string       $name
     * @param string|null  $type
     * @param string|null  $length
     * @return Column
     * @throws \LogicException If table is unknown schema object
     */
    protected function addOrChangeColumn($table, $name, $type = null, $length = null)
    {
        if ($table instanceof Create)
        {
            $table->checkExists(true);

            return $table->addColumn($name, $type, $length);
        }
        else if ($table instanceof Alter)
        {
            if ($table->getColumnDefinition($name))
            {
                return $table->changeColumn($name, $type, $length);
            }

            return $table->addColumn($name, $type, $length);
        }
        else
        {
            throw new \LogicException('Unknown schema DDL type ' . \get_class($table));
        }
    }
}
