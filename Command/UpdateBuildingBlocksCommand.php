<?php

namespace C33s\ConstructionKitBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockHandler;

class UpdateBuildingBlocksCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('c33s:construction-kit:update-blocks')
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
        if (OutputInterface::VERBOSITY_NORMAL == $output->getVerbosity())
        {
            // enforce verbose output by default
            //$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        }

        //$this->getContainer()->get('c33s_construction_kit.config_handler')->refresh();
        $this->getBuildingBlockHandler()->updateBuildingBlocks();
    }

    /**
     * @return BuildingBlockHandler
     */
    protected function getBuildingBlockHandler()
    {
        return $this->getContainer()->get('c33s_construction_kit.building_block_handler');
    }
}
