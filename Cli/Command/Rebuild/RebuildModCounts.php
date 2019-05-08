<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\Rebuild;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildModCounts extends Command
{
    protected function configure()
    {
        $this
            ->setName('xf-rebuild:sv-mod-counts')
            ->setDescription('rebuild various moderator count caches');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \XF\Repository\UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = \XF::repository('XF:UserUpgrade');
        $userUpgradeRepo->rebuildUpgradeCount();

        /** @var \XF\Repository\ApprovalQueue $approvalRepo */
        $approvalRepo = \XF::repository('XF:ApprovalQueue');
        $approvalRepo->rebuildUnapprovedCounts();

        /** @var \XF\Repository\Report $reportRepo */
        $reportRepo = \XF::repository('XF:Report');
        $reportRepo->rebuildReportCounts();

        return 0;
    }
}