<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

/**
 * Trait SubTaskRunnerTrait
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command
 */
trait SubTaskRunnerTrait
{
    /**
     * @param OutputInterface $output
     * @param int $time
     * @throws \Exception
     */
    public function runPendingManualJobsInTask(OutputInterface $output, $time = 30)
    {
        \XF::triggerRunOnce();

        $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);

        \XF::app()->container()->decache('job.manager');
        $this->runSubTask($output, [
            'iau-addon:run-jobs',
            '--manual-jobs',
            '--time=' . $time,
        ]);
    }

    /**
     * @param OutputInterface $output
     * @param array $args
     */
    public function runSubTask(OutputInterface $output, array $args)
    {
        $execFinder = new PhpExecutableFinder();

        $builderOptions = [
            $execFinder->find(false),
            \XF::getRootDirectory() . DIRECTORY_SEPARATOR . 'cmd.php',
            '-n'
        ];
        $builderOptions = array_merge($builderOptions, $args);

        $verbosity = $output->getVerbosity();
        switch ($verbosity) {
            case OutputInterface::VERBOSITY_QUIET:
                $verbosityOption = '-q';
                break;

            case OutputInterface::VERBOSITY_VERBOSE:
                $verbosityOption = '-v';
                break;

            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                $verbosityOption = '-vv';
                break;

            case OutputInterface::VERBOSITY_DEBUG:
                $verbosityOption = '-vvv';
                break;

            default:
                $verbosityOption = '';
                break;
        }

        if ($verbosityOption) {
            $builderOptions[] = $verbosityOption;
        }

        $builder = ProcessBuilder::create($builderOptions);
        $builder->setTimeout(null);
        $process = $builder->getProcess();

        /** @var ProcessHelper $processHelper */
        $processHelper = $this->getHelper('process');

        try {
            $processHelper->mustRun($output, $process, null, function ($type, $data) use ($output) {
                if ($type == Process::OUT) {
                    $output->write($data);
                }
                // Note that progress bar output is in Process::ERR/stderr, but they get streamed to this callback
                // interleaved, so displaying both is difficult. Thus, we need to only display stuff sent stdout.
            });
        } catch (ProcessFailedException $e) {
            $process = $e->getProcess();
            if ($process->getExitCode() === 222) {
                // This indicates that the sub-process threw an exception. It will have been printed and logged
                // so don't trigger the normal exception handling. However, we can't continue so exit.
                exit(1);
            }
        }

    }
}