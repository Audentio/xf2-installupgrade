<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use League\Flysystem\FileNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Repository\FileHandling;
use XF;
use XF\Util\Php;
use function file_exists;
use function is_readable;

/**
 * Class ExtractZip
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn
 */
class ExtractZip extends Command
{
    use BulkCliJobTrait;

    /**
     *
     */
    protected function configure()
    {
        $this
            ->setName('iau:addon-extract-zip')
            ->setAliases(['iau-addon:extract-zip'])
            ->setDescription('Extracts a zip(s) to the add-on directory (seperate filenames with a space).')
            ->addArgument(
                'zip-filenames',
                InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                'Zip filename'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws FileNotFoundException
     * @throws FileNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // be verbose, otherwise we don't get stack trace errors...
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $zipFilenames = $input->getArgument('zip-filenames');
        foreach ($zipFilenames as $zipFilename) {
            if (!file_exists($zipFilename) || !is_readable($zipFilename)) {
                $output->writeln("<error>File {$zipFilename} is not readable</error>");

                return 1;
            }
        }

        /** @var FileHandling $repo */
        $repo = XF::repository('ThemeHouse\InstallAndUpgrade:FileHandling');
        foreach ($zipFilenames as $zipFilename) {
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln("Extracting $zipFilename");
            }
            $zipRoot = $repo->mountZip($zipFilename, 'addon-zip');
            try {
                $repo->copyFiles($zipRoot . '://', XF::getRootDirectory(), function ($file) use ($output) {
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                        $output->writeln($file);
                    }
                });
            } finally {
                $repo->unmountZip($zipRoot);
            }
        }

        Php::resetOpcache();

        return 0;
    }


}