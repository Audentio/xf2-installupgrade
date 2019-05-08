<?php

namespace ThemeHouse\InstallAndUpgrade\Cli\Command\AddOn;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Cli\Command\AddOnSubAction;

class AddOnSubActionFix extends AddOnSubAction
{
    protected function configure()
    {
        parent::configure();
        $this->setName('xf:addon-sub-action-deferred-jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try
        {
            return parent::execute($input, $output);
        }
        finally
        {
            \XF::triggerRunOnce();

            \XF::app()->container()->decache('job.manager');
        }
    }
}