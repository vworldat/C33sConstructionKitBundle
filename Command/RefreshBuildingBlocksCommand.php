<?php

namespace C33s\ConstructionKitBundle\Command;

use C33s\ConstructionKitBundle\Mapping\MappingWriter;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshBuildingBlocksCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('construction-kit:refresh')
            ->setDescription('Update the list of BuildingBlocks and install them automatically.')
            ->addOption(
                'no-auto-install',
                null,
                InputOption::VALUE_NONE,
                'Do not install new blocks automatically'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (OutputInterface::VERBOSITY_NORMAL == $output->getVerbosity()) {
            // enforce verbose output by default
            $output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        $this->getMappingWriter()->refresh($output);
    }

    /**
     * @return MappingWriter
     */
    protected function getMappingWriter()
    {
        return $this->getContainer()->get('c33s_construction_kit.writer');
    }
}
