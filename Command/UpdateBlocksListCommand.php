<?php

namespace C33s\ConstructionKitBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateBlocksListCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('c33s:construction-kit:update-blocks')
            ->setDescription('Update the building blocks list based on the auto-generated C33sBuildingBlocksList class')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_NORMAL == $output->getVerbosity())
        {
            // enforce verbose output by default
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        //$this->getContainer()->get('c33s_construction_kit.config_handler')->refresh();
    }
}
