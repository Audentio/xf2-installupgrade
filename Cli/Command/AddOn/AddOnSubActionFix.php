<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AddOnSubAction;

/**
 * Class AddOnSubActionFix
 * @package ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn
 */
class AddOnSubActionFix extends AddOnSubAction
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();
        $this->setName('xf:addon-sub-action-deferred-jobs');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            return parent::execute($input, $output);
        } finally {
            \XF::triggerRunOnce();

            \XF::app()->container()->decache('job.manager');
        }
    }
}