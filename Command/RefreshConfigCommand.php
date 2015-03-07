<?php

namespace C33s\ConstructionKitBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshConfigCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('c33s:config:refresh')
            ->setDescription('Initialize or refresh the C33sConstructionKitBundle config')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_NORMAL == $output->getVerbosity())
        {
            // enforce verbose output by default
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $this->getContainer()->get('c33s_construction_kit.config_handler')->refresh();
    }
}
