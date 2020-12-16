<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XF;
use XF\Entity\AddOn;
use XF\Repository\Style;

/**
 * Trait BulkCliJobTrait
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command
 */
trait BulkCliJobTrait
{
    /**
     *
     */
    protected function configureBulk()
    {
        $this->addOption(
            'bulk',
            null,
            InputOption::VALUE_NONE,
            'Indicates this is part of a bulk job, and that many finalization tasks should be deferred'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param bool $start
     */
    protected function setupBulkJob(
        /** @noinspection PhpUnusedParameterInspection */
        InputInterface $input,
        OutputInterface $output,
        $start
    ) {
        $app = XF::app();
        $registry = $app->registry();
        if ($start) {
            $bulk = $input->getOption('bulk');
            if ($bulk) {
                $registry->set('svBulkJob', 1);
            }
        } else {
            $db = $app->db();

            // reset is_processing flag which can get stuck
            $db->beginTransaction();
            $addons = XF::app()->finder('XF:AddOn')->where('is_processing', '=', 1)->fetch();
            /** @var AddOn $addon */
            foreach ($addons as $addon) {
                $addon->is_processing = false;
                $addon->saveIfChanged($saved, true, false);
            }
            $db->commit();

            // todo supress triggerRebuildProcessingChange/XF:AddOnUninstallData ??


            // queue permission rebuild, but only if required
            if ($registry->get('svBulkJob.permRebuild')) {
                $app->jobManager()->enqueueUnique('permissionRebuild', 'XF:PermissionRebuild');
            }

            if ($registry->get('svBulkJob.styleRebuild')) {
                /** @var Style $styleRepo */
                $styleRepo = $app->repository('XF:Style');
                $styleRepo->updateAllStylesLastModifiedDate();
            }

            $registry->delete(['svBulkJob', 'svBulkJob.permRebuild', 'svBulkJob.styleRebuild']);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function hasPendingBulkJob(
        /** @noinspection PhpUnusedParameterInspection */
        InputInterface $input,
        OutputInterface $output
    ) {
        $app = XF::app();
        $registry = $app->registry();
        if ($registry->get('svBulkJob') || $registry->get('svBulkJob.permRebuild') || $registry->get('svBulkJob.styleRebuild')) {
            XF::app()->container()->decache('job.manager');
            $output->writeln("Has a pending bulk job, to finish run;\n\tphp cmd.php iau-addon:finish-bulk");

            return true;
        }

        return false;
    }
}