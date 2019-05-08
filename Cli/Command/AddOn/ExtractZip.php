<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractZip extends Command
{
    use BulkCliJobTrait;

    protected function configure()
    {
        $this
            ->setName('addon:extract-zip')
            ->setDescription('Extracts a zip(s) to the add-on directory (seperate filenames with a space)')
            ->addArgument(
                'zip-filenames',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Zip filename'
            )
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL)
        {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $zipFilenames = $input->getArgument('zip-filenames');
        foreach($zipFilenames as $zipFilename)
        {
            if (!\file_exists($zipFilename) || !\is_readable($zipFilename))
            {
                $output->writeln("<error>File {$zipFilename} is not readable</error>");

                return 1;
            }
        }

        /** @var \ThemeHouse\InstallAndUpgrade\Repository\FileHandling $repo */
        $repo = \XF::repository('ThemeHouse\InstallAndUpgrade:FileHandling');
        foreach($zipFilenames as $zipFilename)
        {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
            {
                $output->writeln("Extracting $zipFilename");
            }
            $zipRoot = $repo->mountZip($zipFilename, 'addon-zip');
            try
            {
                $repo->copyFiles($zipRoot . '://', \XF::getRootDirectory(), function ($file) use ($output) {
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE)
                    {
                        $output->writeln($file);
                    }
                });
            }
            finally
            {
                $repo->unmountZip($zipRoot);
            }
        }

        \XF\Util\Php::resetOpcache();

        return 0;
    }


}