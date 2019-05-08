<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\Style;

use ThemeHouse\InstallAndUpgrade\Cli\Command\BulkCliJobTrait;
use ThemeHouse\InstallAndUpgrade\Cli\Command\SubTaskRunnerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EnableDisable extends Command
{
    use BulkCliJobTrait, SubTaskRunnerTrait;

    protected function configure()
    {
        $this
            ->setName('iau:style-enable')
            ->setAliases(['iau:style-disable', 'iau-style:enable', 'iau-style:disable', 'iau-style:toggle', 'iau:style-toggle'])
            ->setDescription('Enables/disables a style.')
            ->addArgument(
                'style',
                InputArgument::REQUIRED,
                'Style Id'
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

        $styleId = (int)$input->getArgument('style');
        /** @var \XF\Entity\Style $style */
        $style = \XF::finder('XF:Style')
                    ->where('style_id', '=', $styleId)
                    ->fetchOne();
        if (!$style)
        {
            $output->writeln("<error>" . "Invalid style; {$styleId}." . "</error>");

            return 1;
        }

        switch($input->getArgument('command'))
        {
            case 'xf-style:enable':
                $style->user_selectable = true;
                $style->saveIfChanged();
                $output->writeln( "{$style->title} enabled.");
                return 0;
            case 'xf-style:disable':
                $style->user_selectable = false;
                $style->saveIfChanged();
                $output->writeln( "{$style->title} disabled.");
                return 0;
            default:
                $output->writeln("<error>" . "Invalid style; {$styleId}." . "</error>");
                return 1;
        }
    }
}