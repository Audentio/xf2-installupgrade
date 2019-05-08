<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdatePending extends Command
{
    use SubTaskRunnerTrait;

    protected function configure()
    {
        $this
            ->setName('addon:update-pending')
            ->setAliases(['addon:upgrade-pending'])
            ->setDescription('Updates pending add-ons')
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL)
        {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $excludedAddons = $input->getArgument('exclude-addon');
        $excludedAddons = \array_fill_keys($excludedAddons, true);
        $includeLegacy = $input->getOption('include-legacy');

        // big hammer to avoid caching issues
        \XF\Util\Php::resetOpcache();

        // discover add-ons
        $allAddOns = \XF::app()->addOnManager()->getAllAddOns();
        /** @var \XF\AddOn\AddOn[] $upgradeableAddOns */
        $upgradeableAddOns = [];
        foreach ($allAddOns as $addon)
        {
            if (isset($excludedAddons[$addon->getAddOnId()]))
            {
                continue;
            }

            if ($addon->canUpgrade() && $addon->isActive())
            {
                $upgradeableAddOns[] = $addon;
            }
            else if ($includeLegacy && $addon->isLegacy())
            {
                /** @noinspection PhpUndefinedFieldInspection */
                $legacyAddonId = $addon->legacy_addon_id;
                if ($legacyAddonId && isset($allAddOns[$legacyAddonId]) && $addon->canUpgrade())
                {
                    $upgradeableAddOns[] = $addon;
                }
            }
        }
        if (!$upgradeableAddOns)
        {
            $output->writeln("Nothing to upgrade");

            return 0;
        }

        $upgradeableAddOns = $this->sortByDependencies($input, $output, $upgradeableAddOns);
        if (!$upgradeableAddOns)
        {
            return 1;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $titles = [];
        foreach ($upgradeableAddOns as $addOn)
        {
            /** @noinspection PhpUndefinedFieldInspection */
            $titles[] = $addOn['title'] . "(" . $addOn->version_string . " => " . $addOn->json_version_string . ")";
        }
        $titleList = join("\n", $titles);
        $question = new ConfirmationQuestion("<question>" . \XF::phrase('th_iau_please_confirm_that_you_want_to_upgrade_following_add_ons') . ":\n" . $titleList . "\n (y/n)</question>");
        $response = $helper->ask($input, $output, $question);
        if (!$response)
        {
            return 1;
        }

        foreach ($upgradeableAddOns as $addOn)
        {
            $this->runSubTask($output, [
                'addon:upgrade',
                $addOn->getAddOnId(),
                '--bulk',
            ]);
        }

        $this->runSubTask($output, [
            'addon:bulk-finish'
        ]);

        return 0;
    }

    /**
     * @param InputInterface    $input
     * @param OutputInterface   $output
     * @param \XF\AddOn\AddOn[] $upgradeableAddOns
     * @return array
     */
    protected function sortByDependencies(/** @noinspection PhpUnusedParameterInspection */
        InputInterface $input, OutputInterface $output, array $upgradeableAddOns)
    {
        $installList = [];
        /** @var \XF\AddOn\AddOn[] $complex */
        $complex = [];
        // init the list
        foreach ($upgradeableAddOns as $addOn)
        {
            $json = $addOn->getJson();
            unset($json['require']['php']);
            unset($json['require']['XF']);
            $installList[$addOn->getAddOnId()] = ['addon' => $addOn, 'dependancies' => []];
            if (!empty($json['require']) || !empty($json['require-soft']))
            {
                $complex[$addOn->getAddOnId()] = $addOn;
            }
        }
        // build the graph
        foreach ($complex as $addOnId => $addOn)
        {
            $json = $addOn->getJson();
            foreach ((array)$json['require'] as $productKey => $requirement)
            {
                if (empty($installList[$productKey]))
                {
                    continue;
                }
                if (empty($installList[$addOnId]['dependancies'][$productKey]))
                {
                    $installList[$addOnId]['dependancies'][$productKey] = &$installList[$productKey];
                }
            }
            // custom install hints
            if (isset($json['require-soft']))
            {
                foreach ((array)$json['require-soft'] as $productKey => $requirement)
                {
                    if (empty($installList[$productKey]))
                    {
                        continue;
                    }
                    if (empty($installList[$addOnId]['dependancies'][$productKey]))
                    {
                        $installList[$addOnId]['dependancies'][$productKey] = &$installList[$productKey];
                    }
                }
            }
        }

        // actually resolve into a list
        $finalList = [];
        $loopDetection = [];
        foreach ($installList as $addOnId => $addOn)
        {
            if ($addOn['dependancies'])
            {
                $finalList = $finalList + $this->resolveDependencies($installList, $addOnId, $loopDetection);
            }

            $finalList[$addOnId] = $addOn['addon'];
        }

        return $finalList;
    }

    /**
     * @param array  $installList
     * @param string $addOnId
     * @param array  $loopDetection
     * @return array
     */
    protected function resolveDependencies(array $installList, $addOnId, array &$loopDetection)
    {
        $loopDetection[$addOnId] = true;
        $finalList = [];
        foreach ($installList[$addOnId]['dependancies'] as $childAddOnId => $addOn)
        {
            if (isset($loopDetection[$childAddOnId]))
            {
                continue;
            }

            if ($addOn['dependancies'])
            {
                $finalList = $finalList + $this->resolveDependencies($installList, $childAddOnId, $loopDetection);
            }

            $finalList[$childAddOnId] = $addOn['addon'];
        }

        return $finalList;
    }
}