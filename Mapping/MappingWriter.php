<?php

namespace C33s\ConstructionKitBundle\Mapping;

use C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockInterface;
use C33s\ConstructionKitBundle\Manipulator\KernelManipulator;
use C33s\SymfonyConfigManipulatorBundle\Manipulator\ConfigManipulator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This class is responsible for updating and storing mapping information.
 */
class MappingWriter
{
    /**
     * @var BuildingBlockMapping
     */
    protected $mapping;

    /**
     * Array holding the raw mapping information provided by the mapping service.
     *
     * @var array
     */
    protected $mappingData;

    /**
     * @var ConfigManipulator
     */
    protected $configManipulator;

    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $blocksToEnable = array();

    /**
     * ConfigManipulator module name used for saving the mapping config.
     *
     * @var string
     */
    protected $moduleName = 'c33s_construction_kit.map';

    public function __construct(BuildingBlockMapping $mapping, ConfigManipulator $configManipulator, KernelInterface $kernel, LoggerInterface $logger)
    {
        $this->mapping = $mapping;
        $this->configManipulator = $configManipulator;
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    /**
     * Update mapping information and save to file.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function refresh(InputInterface $input, OutputInterface $output)
    {
        if (!$this->checkAndEnableConfigFiles()) {
            $message = <<<EOF

    ######################################################
    #                                                    #
    # The symfony configuration has been changed.        #
    #                                                    #
    # Please re-run the construction-kit:refresh command #
    #                                                    #
    ######################################################

EOF;
            $output->writeln($message);

            return;
        }

        $this->toggleBlocks($input, $output);
        $this->saveBlocksMap();
    }

    /**
     * Check if module configs exist and are being included.
     * Activates missing config files.
     *
     * @return bool false if anything was missing
     */
    protected function checkAndEnableConfigFiles()
    {
        $this->logger->info('Refreshing all config files first');
        $this->configManipulator->refreshConfigFiles();

        $mainConfigFile = $this->configManipulator->getConfigFile('');
        $yamlManipulator = $this->configManipulator->getYamlManipulator();

        $modules = array('c33s_construction_kit.composer', 'c33s_construction_kit.map');
        $result = true;
        foreach ($modules as $module) {
            $filename = $this->configManipulator->getModuleFile($module, '', true);
            $shortFilename = $this->configManipulator->getModuleFile($module, '', false);

            $this->logger->info('  Checking file '.$shortFilename);
            if (!file_exists($filename)) {
                $this->logger->warning('  File does not exist, creating empty one.');
                touch($filename);
                $result = false;
            }

            if (!$yamlManipulator->importerFileHasFilename($mainConfigFile, $shortFilename)) {
                $this->logger->warning('  File was not included in main config file, adding '.$shortFilename.' to config.yml');
                $this->configManipulator->enableModuleConfig($module, '');
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Get mapping data.
     *
     * @return array
     */
    protected function getMappingData()
    {
        if (null === $this->mappingData) {
            $this->mappingData = $this->mapping->getUpdatedMappingData();
        }

        return $this->mappingData;
    }

    protected function updateMappingData($data)
    {
        $this->mappingData = $data;
    }

    protected function getDefaultsImporterModuleName()
    {
        return '_building_block_defaults';
    }

    /**
     * Enable or disable blocks based on configuration.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function toggleBlocks(InputInterface $input, OutputInterface $output)
    {
        $newMap = $this->getMappingData();

        $bundlesToEnable = array();
        foreach ($newMap['building_blocks'] as $class => $settings) {
            $block = $this->mapping->getBlock($class);
            $block->setKernel($this->kernel);

            if ($settings['enabled']) {
                $bundlesToEnable = array_merge($bundlesToEnable, $this->enableBlock(
                    $block,
                    $settings['use_config'],
                    $settings['force_init'],
                    $input,
                    $output
                ));
            } else {
                $this->disableBlock($block);
            }
        }

        $bundlesToEnable = array_unique($bundlesToEnable);
        $this->enableBundles($bundlesToEnable);
    }

    /**
     * Disable a previously enabled building block.
     * This is not implemented yet. Please feel free to provide ideas how to accomplish this.
     *
     * @param BuildingBlockInterface $block
     */
    protected function disableBlock(BuildingBlockInterface $block)
    {
    }

    /**
     * Enable a building block.
     *
     * @param BuildingBlockInterface $block
     * @param bool                   $useConfig
     * @param bool                   $init
     *
     * @return array List of bundles to enable for this block
     */
    protected function enableBlock(
        BuildingBlockInterface $block,
        $useConfig,
        $init,
        InputInterface $input,
        OutputInterface $output
    ) {
        if ($init) {
            $this->logger->info('Initializing '.get_class($block));
            if ($useConfig) {
                $comment = 'Added by '.get_class($block).' init()';
                foreach ($block->getInitialParameters() as $name => $defaultValue) {
                    $this->configManipulator->addParameter($name, $defaultValue, $comment);
                    $comment = null;
                }
            }

            $block->init($input, $output);
            $this->markAsInitialized($block);
        }

        $info = $this->mapping->getBlockInfo($block);

        if ($useConfig) {
            $comment = 'Added by '.get_class($block);
            foreach ($block->getAddParameters() as $name => $defaultValue) {
                if ($init || !$this->kernel->getContainer()->hasParameter($name)) {
                    $this->configManipulator->addParameter($name, $defaultValue, $comment);
                    $comment = null;
                }
            }

            $usedModules = array();
            foreach ($info['config_templates'] as $env => $templates) {
                foreach ($templates as $relative => $template) {
                    $module = basename($template, '.yml');
                    if ($this->configManipulator->checkCanCreateModuleConfig($module, $env, false)) {
                        $content = file_get_contents($template);
                        $this->configManipulator->addModuleConfig($module, $content, $env);
                    }

                    $usedModules[$env][$module] = true;
                }
            }

            foreach ($info['default_configs'] as $env => $defaults) {
                foreach ($defaults as $relative => $default) {
                    // this is the file that will hold all defaults imports per environment
                    $defaultsImporterFile = $this->configManipulator->getModuleFile($this->getDefaultsImporterModuleName(), $env);

                    // first add the imported defaults file to the defaults importer (e.g. config/_building_block_defaults.yml)
                    $this->configManipulator->getYamlManipulator()->addImportFilenameToImporterFile($defaultsImporterFile, $relative);

                    // now as the defaults importer exists we may add it to the main config file
                    $this->configManipulator->enableModuleConfig($this->getDefaultsImporterModuleName(), $env);

                    $module = basename($default, '.yml');
                    if (!isset($usedModules[$env][$module]) && $this->configManipulator->checkCanCreateModuleConfig($module, $env)) {
                        // any modules that only use a defaults file will be provided with a commented version of the given file.
                        $content = file_get_contents($default);
                        $content = '#'.preg_replace("/\n/", "\n#", $content);
                        $content = '# This file was auto-generated based on '.$relative."\n# Feel free to change anything you have to.\n\n".$content;
                        $this->configManipulator->addModuleConfig($module, $content, $env, true);
                    }
                }
            }
        }

        return $info['bundle_classes'];
    }

    protected function markAsInitialized(BuildingBlockInterface $block)
    {
        $newMap = $this->getMappingData();
        $newMap['building_blocks'][get_class($block)]['force_init'] = false;

        $this->updateMappingData($newMap);
    }

    /**
     * Add the given bundle class to AppKernel.php, no matter if it is already in there or not. The KernelManipulator will take care of this.
     *
     * @param string $bundleClass
     */
    protected function enableBundles($bundleClasses)
    {
        try {
            $manipulator = new KernelManipulator($this->kernel);
            $manipulator->addBundles($bundleClasses);

            $this->logger->info('Adding '.implode(', ', $bundleClasses).' to AppKernel');
        } catch (\RuntimeException $e) {
        }
    }

    /**
     * Check if the given class name was enabled in the previous configuration.
     *
     * @param string $class
     *
     * @return bool
     */
    protected function wasEnabled($class)
    {
        return isset($this->existingMapping['building_blocks'][$class]['enabled']) && $this->existingMapping['building_blocks'][$class]['enabled'];
    }

    /**
     * Save building block map to specific yaml config file.
     */
    protected function saveBlocksMap()
    {
        $data = array(
            'c33s_construction_kit' => array(
                'mapping' => $this->getMappingData(),
            ),
        );

        $content = <<<EOF
# This file is auto-updated each time construction-kit:refresh is called.
# This may happen automatically during various composer events (install, update)
#
# Follow these rules for your maximum building experience:
#
# [*] Only edit existing block classes in this file. If you need to add another custom building block class use the
#     composer extra 'c33s-building-blocks' or register your block as a tagged service (tag 'c33s_building_block').
#     Make sure your block implements C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockInterface
#
# [*] You can enable or disable a full building block by simply setting the "enabled" flag to true or false, e.g.:
#     C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
#         enabled: true
#
#     If you enable a block for the first time, make sure the "force_init" flag is also set
#     C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
#         enabled: true
#         force_init: true
#
# [*] "use_config" and "use_assets" flags will only be used if block is enabled. They do not affect disabled blocks.
#
# [*] Asset lists will automatically be filled by all assets of asset-enabled blocks. To exclude specific assets, move them to their
#     respective "disabled" sections. You may also reorder assets - the order will be preserved.
#
# [*] Assets are made available through assetic using the @asset_group notation.
#
# [*] Custom YAML comments in this file will be lost!
#

EOF;

        $content .= Yaml::dump($data, 6);

        // force remove empty asset arrays to ease copy&paste of YAML lines
        $content = str_replace('                disabled: {  }', '                disabled:', $content);
        $content = str_replace('                enabled: {  }', '                enabled:', $content);
        $content = str_replace('                filters: {  }', '                filters:', $content);

        $this->configManipulator->addModuleConfig('c33s_construction_kit.map', $content, '', true);
    }
}
