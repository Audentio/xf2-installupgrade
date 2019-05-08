<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\Style;

use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command
{
    use BulkCliJobTrait, SubTaskRunnerTrait;

    protected function configure()
    {
        $this
            ->setName('xf-style:import')
            ->setDescription('Imports a style XML file as a new style or over an existing one.')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Style XML file'
            )
            ->addOption(
                'overwrite-style-id',
                null,
                InputOption::VALUE_REQUIRED,
                'The style to overwrite'
            )
            ->addOption(
                'new-style-parent-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'The parent for a new style'
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

        $filename = $input->getArgument('file');
        try
        {
            $xml = \XF\Util\Xml::openFile($filename, true);
        }
        catch (\Exception $e)
        {
            $xml = null;
        }
        if (!$xml)
        {
            $output->writeln("<error>" . "Could not load style XML file; {$filename}" . "</error>");

            return 1;
        }

        /** @var \XF\Service\Style\Import $service */
        $service = \XF::service('XF:Style\Import');

        if (!$service->isValidXml($xml))
        {
            $output->writeln("<error>" . "Invalid XML file; {$filename}" . "</error>");

            return 1;
        }

        $overwriteId = (int)$input->getOption('overwrite-style-id');
        if ($overwriteId)
        {
            /** @var \XF\Entity\Style $style */
            $style = \XF::finder('XF:Style')
                        ->where('style_id', '=', $overwriteId)
                        ->fetchOne();
            if (!$style)
            {
                $output->writeln("<error>" . "Invalid style to override; {$overwriteId}." . "</error>");

                return 1;
            }
            $service->setOverwriteStyle($style);
        }
        else
        {
            $parentId = (int)$input->getOption('new-style-parent-id');
            if ($parentId)
            {
                /** @var \XF\Entity\Style $style */
                $style = \XF::finder('XF:Style')
                            ->where('style_id', '=', $parentId)
                            ->fetchOne();
                if (!$style)
                {
                    $output->writeln("<error>" . "Invalid parent style; {$parentId}." . "</error>");

                    return 1;
                }

                $service->setParentStyle($style);
            }
        }

        $this->setupBulkJob($input, $output, true);

        $s = \microtime(true);
        $service->importFromXml($xml);
        $seconds = round(\microtime(true) - $s, 2);
        $filename = \basename($filename);
        $output->writeln("Imported {$filename} in {$seconds} seconds");

        if (!$this->hasPendingBulkJob($input, $output))
        {
            $this->runPendingManualJobsInTask($output, -1);
        }

        return 0;
    }
}