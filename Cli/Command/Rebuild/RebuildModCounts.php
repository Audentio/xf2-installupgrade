<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\Rebuild;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF;
use XF\Repository\ApprovalQueue;
use XF\Repository\Report;
use XF\Repository\UserUpgrade;

/**
 * Class RebuildModCounts
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\Rebuild
 */
class RebuildModCounts extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:rebuild-sv-mod-counts')
            ->setAliases(['iau-rebuild:sv-mod-counts'])
            ->setDescription('Rebuilds various moderator count caches.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = XF::repository('XF:UserUpgrade');
        $userUpgradeRepo->rebuildUpgradeCount();

        /** @var ApprovalQueue $approvalRepo */
        $approvalRepo = XF::repository('XF:ApprovalQueue');
        $approvalRepo->rebuildUnapprovedCounts();

        /** @var Report $reportRepo */
        $reportRepo = XF::repository('XF:Report');
        $reportRepo->rebuildReportCounts();

        return 0;
    }
}