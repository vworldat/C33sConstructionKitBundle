<?php

namespace C33s\ConstructionKitBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use C33s\ConstructionKitBundle\Config\ConfigHandler;

class RefreshConfigCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('construction-kit:refresh-config')
            ->setDescription('Initialize or refresh the C33sConstructionKitBundle config')
            ->addOption(
                'enable-config',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Enable the given config files in default environment',
                array()
            )
            ->addOption(
                'add-config',
                null,
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Add the given config files in default environment if it does not exist yet',
                array()
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

        $this->getConfigHandler()->refresh();

        foreach ($input->getOption('enable-config') as $module)
        {
            $this->getConfigHandler()->enableModuleConfig($module);
        }

        foreach ($input->getOption('add-config') as $module)
        {
            if ($this->getConfigHandler()->checkCanCreateModuleConfig($module))
            {
                $this->getConfigHandler()->addModuleConfig($module, '');
            }
        }
    }

    /**
     *
     * @return ConfigHandler
     */
    protected function getConfigHandler()
    {
        return $this->getContainer()->get('c33s_construction_kit.config_handler');
    }
}
