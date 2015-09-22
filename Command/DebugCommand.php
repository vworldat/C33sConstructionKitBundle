<?php

namespace C33s\ConstructionKitBundle\Command;

use C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockHandler;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DebugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('debug:construction-kit')
            ->setDescription('Display C33sConstructionKitBundle building blocks information')
            ->addArgument(
                'blocks',
                InputArgument::IS_ARRAY,
                "List of block classes to show details for. You may use the full class names or parts of them.\nNot case-sensitive, / is allowed in place of \\"
            )
            ->addOption(
                'details',
                'd',
                InputOption::VALUE_NONE,
                'Show details for all available building blocks'
            )
            ->setAliases(array(
                'construction-kit:debug',
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getBuildingBlockHandler()->debug($output, $input->getArgument('blocks'), $input->getOption('details'));
    }

    /**
     * @return BuildingBlockHandler
     */
    protected function getBuildingBlockHandler()
    {
        return $this->getContainer()->get('c33s_construction_kit.building_block_handler');
    }
}
