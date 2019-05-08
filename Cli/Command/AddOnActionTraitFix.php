<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use XF\Cli\Command\AddOnActionTrait;
use XF\AddOn\AddOn;

trait AddOnActionTraitFix
{
    use AddOnActionTrait
    {
        runSubAction as protected runSubActionOld;
    }

    public function runSubAction(OutputInterface $output, AddOn $addOn, $action)
    {
        $execFinder = new PhpExecutableFinder();

        $builderOptions = [
            $execFinder->find(false),
            \XF::getRootDirectory() . DIRECTORY_SEPARATOR . 'cmd.php',
            'xf:addon-sub-action-deferred-jobs',
            $addOn->addon_id,
            $action,
            '--k=' . $this->getSubActionKey($addOn->addon_id, $action)
        ];

        if ($verbosityOption = $this->getVerbosityOption($output->getVerbosity()))
        {
            $builderOptions[] = $verbosityOption;
        }

        if (\XF::$versionId >= 2010000)
        {
            /** @noinspection PhpParamsInspection */
            $process = new Process($builderOptions);
            $process->setTimeout(null);
            if (is_callable([$process, 'inheritEnvironmentVariables']))
            {
                /** @noinspection PhpUndefinedMethodInspection */
                $process->inheritEnvironmentVariables();
            }
            $process->setCommandLine($process->getCommandLine());
        }
        else
        {
            /** @noinspection PhpDeprecationInspection */
            $builder = \Symfony\Component\Process\ProcessBuilder::create($builderOptions);
            $builder->setTimeout(null);
            $process = $builder->getProcess();
        }

        /** @var ProcessHelper $processHelper */
        $processHelper = $this->getHelper('process');

        try
        {
            $processHelper->mustRun($output, $process, null, function ($type, $data) use ($output) {
                if ($type == Process::OUT)
                {
                    $output->write($data);
                }
                // Note that progress bar output is in Process::ERR/stderr, but they get streamed to this callback
                // interleaved, so displaying both is difficult. Thus, we need to only display stuff sent stdout.
            });
        }
        catch (ProcessFailedException $e)
        {
            $process = $e->getProcess();
            if ($process->getExitCode() === 222)
            {
                // This indicates that the sub-process threw an exception. It will have been printed and logged
                // so don't trigger the normal exception handling. However, we can't continue so exit.
                exit(1);
            }
        }
    }
}