<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BulkFinish extends Command
{
    use BulkCliJobTrait, SubTaskRunnerTrait;

    protected function configure()
    {
        $this
            ->setName('iau:addon-bulk-finish')
            ->setAliases(['iau:addon-finish-bulk', 'iau-addon:finish-bulk', 'iau-addon:bulk-finish'])
            ->setDescription('Finishes a bulk install/upgrade/uninstall job.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL)
        {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        // big hammer to avoid caching issues
        \XF\Util\Php::resetOpcache();

        $this->setupBulkJob($input, $output, false);

        $this->runPendingManualJobsInTask($output, -1);

        return 0;
    }
}