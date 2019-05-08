<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunJobs extends Command
{
    protected function configure()
    {
        $this
            ->setName('xf:run-jobs')
            ->setAliases(['addon:run-jobs'])
            ->setDescription('Run pending non-manual jobs in the job queue')
            ->addOption(
                'time',
                null,
                InputOption::VALUE_OPTIONAL,
                "Time-limit for the job runner",
                30
            )
            ->addOption(
                'manual-jobs',
                null,
                InputOption::VALUE_NONE,
                "Run manual jobs"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $quietFlag = OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_QUIET;
        $flags = OutputInterface::OUTPUT_NORMAL | OutputInterface::VERBOSITY_VERBOSE;

        $app = \XF::app();
        if (\XF::$versionId != $app->options()->currentVersionId)
        {
            $output->writeln("<error>Version mismatch - upgrade pending?</error>", $flags);

            return 1;
        }

        $jobManager = $app->jobManager();
        $maxJobRunTime = intval($app->config('jobMaxRunTime'));
        $maxRunTime = intval($input->getOption('time')) ?: 30;
        $includeManualJobs = (bool)intval($input->getOption('manual-jobs'));

        $start = microtime(true);
        do
        {
            $this->runQueue($output, $includeManualJobs, $maxJobRunTime);
            $more = $jobManager->queuePending($includeManualJobs);
            // keep memory usage down
            \XF::app()->em()->clearEntityCache();
        }
        while ($more && ($maxRunTime < 0 || (microtime(true) - $start < $maxRunTime)));

        if ($more)
        {
            $output->writeln("<info>Maximum runtime ({$maxRunTime} seconds) reached with more runnable jobs pending</info>", $quietFlag);

            return 1;
        }
        else
        {
            $output->writeln("<info>No more runnable jobs pending</info>", $quietFlag);

            return 0;
        }
    }

    public function runQueue(OutputInterface $output, $manual, $maxRunTime)
    {
        if ($maxRunTime < 2)
        {
            $maxRunTime = 2;
        }

        $jobManager = \XF::app()->jobManager();

        $runnable = $jobManager->getRunnable($manual);
        $startTime = microtime(true);
        $result = null;

        foreach ($runnable AS $job)
        {
            $remaining = $maxRunTime - (microtime(true) - $startTime);
            if ($remaining < 1)
            {
                break;
            }

            $result = $jobManager->runJobEntry($job, $remaining);

            if ($result && $output)
            {
                $output->writeln((string)$result->statusMessage);
            }
        }

        return $result;
    }
}