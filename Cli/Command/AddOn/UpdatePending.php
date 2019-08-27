<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use XF\Util\Php;

/**
 * Class UpdatePending
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn
 */
class UpdatePending extends Command
{
    use SubTaskRunnerTrait;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:addon-update-pending')
            ->setAliases(['iau-addon:upgrade-pending', 'iau-addon:update-pending', 'iau:addon-upgrade-pending'])
            ->setDescription('Updates pending add-ons.')
            ->addArgument(
                'exclude-addon',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'Exclude an add-on from being updated'
            )
            ->addOption(
                'include-legacy',
                null,
                InputOption::VALUE_NONE,
                "Include legacy add-ons to upgrade"
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

        $excludedAddons = $input->getArgument('exclude-addon');
        $excludedAddons = \array_fill_keys($excludedAddons, true);
        $includeLegacy = $input->getOption('include-legacy');

        // big hammer to avoid caching issues
        Php::resetOpcache();

        // discover add-ons
        $allAddOns = \XF::app()->addOnManager()->getAllAddOns();
        /** @var \XF\AddOn\AddOn[] $upgradeableAddOns */
        $upgradeableAddOns = [];
        foreach ($allAddOns as $addon) {
            if (isset($excludedAddons[$addon->getAddOnId()])) {
                continue;
            }

            if ($addon->canUpgrade() && $addon->isActive()) {
                $upgradeableAddOns[] = $addon;
            } else {
                if ($includeLegacy && $addon->isLegacy()) {
                    /** @noinspection PhpUndefinedFieldInspection */
                    $legacyAddonId = $addon->legacy_addon_id;
                    if ($legacyAddonId && isset($allAddOns[$legacyAddonId]) && $addon->canUpgrade()) {
                        $upgradeableAddOns[] = $addon;
                    }
                }
            }
        }
        if (!$upgradeableAddOns) {
            $output->writeln("Nothing to upgrade");

            return 0;
        }

        /** @var \ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade $repo */
        $repo = \XF::repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
        $upgradeableAddOns = $repo->sortByDependencies($upgradeableAddOns);
        if (!$upgradeableAddOns) {
            return 1;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $titles = [];
        foreach ($upgradeableAddOns as $addOn) {
            /** @noinspection PhpUndefinedFieldInspection */
            $titles[] = $addOn['title'] . "(" . $addOn->version_string . " => " . $addOn->json_version_string . ")";
        }
        $titleList = join("\n", $titles);
        $question = new ConfirmationQuestion("<question>" . \XF::phrase('th_iau_please_confirm_that_you_want_to_upgrade_following_add_ons') . ":\n" . $titleList . "\n (y/n)</question>");
        $response = $helper->ask($input, $output, $question);
        if (!$response) {
            return 1;
        }

        foreach ($upgradeableAddOns as $addOn) {
            $this->runSubTask($output, [
                'iau-addon:upgrade',
                $addOn->getAddOnId(),
                '--bulk',
            ]);
        }

        $this->runSubTask($output, [
            'iau-addon:bulk-finish'
        ]);

        return 0;
    }
}