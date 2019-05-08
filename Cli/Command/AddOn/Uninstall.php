<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use ThemeHouse\InstallAndUpgrade\Cli\Command\AddOnActionTraitFix;
use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Uninstall extends Command
{
    use AddOnActionTraitFix, BulkCliJobTrait, SubTaskRunnerTrait;

    protected function configure()
    {
        $this
            ->setName('iau:addon-uninstall')
            ->setAliases(['iau-addon:uninstall'])
            ->setDescription('Uninstalls the specified add-on.')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Add-On ID'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip verifying that the add-on is uninstallable'
            )
            ;
        $this->configureBulk();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL)
        {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $id = $input->getArgument('id');

        // big hammer to avoid caching issues
        \XF\Util\Php::resetOpcache();

        $addOn = $this->checkInstalledAddOn($id, $error);
        if (!$addOn)
        {
            $output->writeln('<error>' . $error . '</error>');

            return 1;
        }

        if (!$addOn->canUninstall() && !$input->getOption('force'))
        {
            $output->writeln("<error>" . \XF::phrase('this_add_on_cannot_be_uninstalled_like_files_missing') . "</error>");

            return 1;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        /** @noinspection PhpUndefinedFieldInspection */
        $question = new ConfirmationQuestion("<question>" . \XF::phrase('please_confirm_that_you_want_to_uninstall_following_add_on') . ': (' . $addOn->title . ' ' . $addOn->version_string . ") (y/n)</question>");
        $response = $helper->ask($input, $output, $question);
        if (!$response)
        {
            return 1;
        }

        $this->setupBulkJob($input, $output, true);

        $addOn->preUninstall();

        $this->runSubAction($output, $addOn, 'uninstall');
        if (\XF::$versionId > 2010000)
        {
            $this->runSubAction($output, $addOn, 'uninstall-data');
        }
        $this->runSubAction($output, $addOn, 'post-uninstall');

        if (!$this->hasPendingBulkJob($input, $output))
        {
            $this->runPendingManualJobsInTask($output, -1);
        }

        return 0;
    }
}