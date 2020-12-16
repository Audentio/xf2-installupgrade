<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use Exception;
use FilesystemIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ThemeHouse\InstallAndUpgrade\Cli\Command\AddOnActionTraitFix;
use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use XF;
use XF\AddOn\AddOn;
use XF\Util\File;
use XF\Util\Php;

/**
 * Class InstallUpgrade
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn
 */
class InstallUpgrade extends Command
{
    use AddOnActionTraitFix, BulkCliJobTrait, SubTaskRunnerTrait;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:addon-install')
            ->setAliases(['iau-addon:upgrade', 'iau-addon:install', 'iau:addon-upgrade'])
            ->setDescription('Installs or upgrades an add-on.')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Add-On ID'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip verifying that the add-on is installable'
            );
        $this->configureBulk();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        // big hammer to avoid caching issues
        Php::resetOpcache();

        $id = $input->getArgument('id');

        $addOnManager = XF::app()->addOnManager();
        $addOn = $addOnManager->getById($id);

        if (!$addOn) {
            $output->writeln("<error>" . "No add-on with ID '$id' could be found." . "</error>");

            return 1;
        }

        $isInstall = true;
        if (!$addOn->canInstall()) {
                $addOn = $this->checkInstalledAddOn($id, $error);
                if (!$addOn) {
                    $output->writeln('<error>' . $error . '</error>');

                    return 1;
                }

                if (!$addOn->canUpgrade() && !$input->getOption('force')) {
                    $output->writeln("<error>" . XF::phrase('this_add_on_cannot_be_upgraded') . "</error>");

                    return 1;
                }

                $isInstall = false;
        }

        if (!$this->verifyAddOnAction($input, $output, $addOn)) {
            $output->writeln("\tFor {$addOn->title}");
            return 1;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if ($isInstall) {
            $question = new ConfirmationQuestion("<question>" . XF::phrase('please_confirm_that_you_want_to_install_following_add_on') . ': (' . $addOn->title . ' ' . $addOn->version_string . ") (y/n)</question>");

            $response = $helper->ask($input, $output, $question);
            if (!$response) {
                return 1;
            }

            $this->setupBulkJob($input, $output, true);

            // make sure any errors get logged here
            XF::app()->error()->setIgnorePendingUpgrade(true);

            $addOn->preInstall();

            $addOnEntity = $addOn->getInstalledAddOn();
            $addOnEntity->is_processing = false;
            $addOnEntity->saveIfChanged();

            $hasOutputFolder = $addOn->isDevOutputAvailable();
            $this->runSubAction($output, $addOn, 'install');
            if (!$hasOutputFolder && $addOn->isDevOutputAvailable() && $this->isAddOnDataAvailable($addOn)) {
                // _output actions generated in installer, this can cause silent data truncation!!
                $outputPath = $addOn->getAddOnDirectory() . DIRECTORY_SEPARATOR . '_output';
                File::deleteDirectory($outputPath);
            }
            $this->runSubAction($output, $addOn, 'import');
            $this->runSubAction($output, $addOn, 'post-install');
        } else {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            $phrase = XF::phrase('upgrading_x_from_y_to_z', [
                'title' => $addOn->title,
                'old' => $addOn->version_string,
                'new' => $addOn->json_version_string
            ]);

            $output->writeln($phrase->render() . '...');

            $question = new ConfirmationQuestion(
                "<question>Confirm upgrade? (y/n)</question>"
            );

            $response = $helper->ask($input, $output, $question);
            if (!$response) {
                return 1;
            }

            $this->setupBulkJob($input, $output, true);

            // make sure any errors get logged here
            XF::app()->error()->setIgnorePendingUpgrade(true);

            $addOn->preUpgrade();

            $addOnEntity = $addOn->getInstalledAddOn();
            $addOnEntity->is_processing = false;
            $addOnEntity->saveIfChanged();

            $hasOutputFolder = $addOn->isDevOutputAvailable();
            $this->runSubAction($output, $addOn, 'upgrade');
            if (!$hasOutputFolder && $addOn->isDevOutputAvailable() && $this->isAddOnDataAvailable($addOn)) {
                // _output actions generated in installer, this can cause silent data truncation!!
                $outputPath = $addOn->getAddOnDirectory() . DIRECTORY_SEPARATOR . '_output';
                File::deleteDirectory($outputPath);
            }
            $this->runSubAction($output, $addOn, 'import');
            $this->runSubAction($output, $addOn, 'post-upgrade');
        }

        if (!$this->hasPendingBulkJob($input, $output)) {
            $this->runPendingManualJobsInTask($output, -1);
        }

        return 0;
    }

    /**
     * @param AddOn $addOn
     * @return bool
     */
    public function isAddOnDataAvailable(AddOn $addOn)
    {
        $addOnIdDir = $addOn->getDataDirectory();

        if (!file_exists($addOnIdDir)) {
            return false;
        }

        // Returns false  if the directory is totally empty
        return (new FilesystemIterator($addOnIdDir))->valid();
    }
}