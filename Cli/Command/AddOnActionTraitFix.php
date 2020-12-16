<?php /** @noinspection PhpDeprecationInspection */

namespace ThemeHouse\InstallAndUpgrade\Cli\Command;

use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use XF;
use XF\AddOn\AddOn;
use XF\Cli\Command\AddOnActionTrait;

/**
 * Trait AddOnActionTraitFix
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command
 */
trait AddOnActionTraitFix
{
    use AddOnActionTrait {
        runSubAction as protected runSubActionOld;
    }

    /**
     * @param OutputInterface $output
     * @param AddOn $addOn
     * @param $action
     */
    public function runSubAction(OutputInterface $output, AddOn $addOn, $action)
    {
        $execFinder = new PhpExecutableFinder();

        $builderOptions = [
            $execFinder->find(false),
            XF::getRootDirectory() . DIRECTORY_SEPARATOR . 'cmd.php',
            'xf:addon-sub-action-deferred-jobs',
            $addOn->getAddOnId(),
            $action,
            '--k=' . $this->getSubActionKey($addOn->getAddOnId(), $action)
        ];

        if ($verbosityOption = $this->getVerbosityOption($output->getVerbosity())) {
            $builderOptions[] = $verbosityOption;
        }

        if (XF::$versionId >= 2010000) {
            $process = new Process($builderOptions);
            $process->setTimeout(null);
            if (is_callable([$process, 'inheritEnvironmentVariables'])) {
                $process->inheritEnvironmentVariables();
            }
            $process->setCommandLine($process->getCommandLine());
        } else {
            /** @noinspection PhpDeprecationInspection */
            $builder = ProcessBuilder::create($builderOptions);
            $builder->setTimeout(null);
            $process = $builder->getProcess();
        }

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