<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\Admin;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CronList
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\Admin
 */
class CronList extends Command
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:cron-list')
            ->setAliases(['iau-admin:cron-list', 'iau-cron:list'])
            ->setDescription('Lists all cron tasks.')
            ->addOption(
                'active',
                null,
                InputOption::VALUE_NONE,
                "Only active tasks"
            )
            ->addOption(
                'disabled',
                null,
                InputOption::VALUE_NONE,
                "Only disabled tasks"
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $active = $input->getOption('active');
        $disabled = $input->getOption('disabled');

        $finder = \XF::finder('XF:CronEntry');
        if ($active) {
            $finder->where('active', '=', 1);
        }
        if ($disabled) {
            $finder->where('active', '=', 0);
        }
        /** @var \XF\Entity\CronEntry $cron */
        $cronEntries = $finder->fetch();
        foreach ($cronEntries as $cron) {
            $output->writeln($cron->entry_id . "," . $cron->title . "," . "," . strval($cron->active ? 1 : 0));
        }

        return 0;
    }
}