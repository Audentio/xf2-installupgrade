<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ThemeHouse\InstallAndUpgrade\Repository\DbLogQuery;
use XF;
use function is_callable;
use function microtime;

/**
 * Class RunJob
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command
 */
class RunJob extends Command
{

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:run-job')
            ->setAliases(['iau-addon:run-job'])
            ->setDescription('Run a manual job until it is done.')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                "The id of a specific manual job to run",
                null
            );
        if (is_callable('meminfo_dump')) {
            $this->addOption(
                'mem-dump',
                null,
                InputOption::VALUE_NONE,
                'Support https://github.com/BitOne/php-meminfo extension to dump memory snapshots for debugging memory usage issues'
            );
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $flags = OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE;

        $app = XF::app();
        if (XF::$versionId != $app->options()->currentVersionId) {
            $output->writeln("<error>Version mismatch - upgrade pending?</error>", $flags);

            return 1;
        }

        $id = $input->getArgument('id');

        $this->runJob($id, $input, $output);

        return 0;
    }

    /**
     * @param $uniqueId
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function runJob($uniqueId, InputInterface $input, OutputInterface $output)
    {
        $jobManager = XF::app()->jobManager();
        $app = XF::app();
        $em = $app->em();

        if (function_exists('pcntl_signal') && defined('SIGINT')) {
            // Where possible, register a signal handler to run on interrupt to cancel the unique job
            pcntl_signal(SIGINT, function () use ($uniqueId) {
                XF::app()->jobManager()->cancelUniqueJob($uniqueId);
            });
        }

        // after a minute, disabling DB query logging
        $longRunning = false;
        $target = microtime(true) + 60;
        $snapshot = 0;
        $meminfoDump = is_callable('meminfo_dump') && intval($input->getOption('mem-dump'));

        while ($runner = $jobManager->runUnique($uniqueId, XF::config('jobMaxRunTime'))) {
            if ($output) {
                $output->writeln((string)$runner->statusMessage);
            }

            // keep the memory limit down on long running jobs
            $em->clearEntityCache();
            if ($longRunning) {
                $app->container()->decache('permission.cache');
                if ($meminfoDump) {
                    /** @noinspection PhpUndefinedFunctionInspection */
                    meminfo_dump(fopen('/tmp/run-jobs-' . $uniqueId . '-' . $snapshot . '.json', 'w'));
                    $snapshot++;
                }
            } else {
                if ($target < microtime(true)) {
                    $longRunning = true;
                    $db = XF::db();
                    $db->logQueries(false, null);
                    DbLogQuery::resetQueryLog($db);
                }
            }

            if (function_exists('pcntl_signal_dispatch')) {
                // Dispatch any registered signal handlers for pending signals
                pcntl_signal_dispatch();
            }
        }
    }
}