<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use C33s\ConstructionKitBundle\Config\ConfigHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use C33s\ConstructionKitBundle\Manipulator\KernelManipulator;

class BuildingBlockHandler
{
    /**
     *
     * @var KernelInterface
     */
    protected $kernel;

    /**
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var ConfigHandler
     */
    protected $configHandler;

    /**
     *
     * @var array
     */
    protected $composerBuildingBlockClasses;

    protected $existingBlocksMap;

    protected $buildingBlocks = array();

    protected $blocksToEnable = array();

    protected $environments;

    /**
     *
     * @param string $rootDir   Kernel root dir
     */
    public function __construct(KernelInterface $kernel, LoggerInterface $logger, ConfigHandler $configHandler, $composerBuildingBlocks, $blocksMap, array $environments)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->configHandler = $configHandler;
        $this->composerBuildingBlockClasses = $composerBuildingBlocks;
        $this->existingBlocksMap = $blocksMap;
        $this->environments = $environments;
    }

    public function addBuildingBlock(BuildingBlockInterface $block)
    {
        $this->buildingBlocks[get_class($block)] = $block;
    }

    public function updateBuildingBlocks()
    {
        $this->loadComposerBuildingBlocks();
        $newMap = $this->detectChanges();
        $this->activateBlocks($newMap);
        $this->saveBlocksMap($newMap);
    }

    /**
     * Get BuildingBlocks defined by composer
     *
     * @return BuildingBlockInterface[]
     */
    protected function loadComposerBuildingBlocks()
    {
        foreach ($this->composerBuildingBlockClasses as $package => $classes)
        {
            foreach ($classes as $class)
            {
                if (0 !== strpos($class, '\\'))
                {
                    // make sure the class always starts with a backslash to reduce danger of having duplicate classes
                    $class = '\\'.$class;
                }

                if (!class_exists($class))
                {
                    throw new \InvalidArgumentException("The building block class '$class' defined by composer package '$package' does not exist or cannot be accessed.");
                }
                if (!array_key_exists('C33s\\ConstructionKitBundle\\BuildingBlock\\BuildingBlockInterface', class_implements($class)))
                {
                    throw new \InvalidArgumentException("The building block class '$class' defined by composer package '$package' does not implement BuildingBlockInterface.");
                }

                $this->addBuildingBlock(new $class());
            }
        }
    }

    /**
     * At this point all available blocks are collected inside $this->buildingBlocks. The existing class map is placed in $this->existingBlocksMap.
     * We have to detect both new and removed blocks and act accordingly.
     */
    protected function detectChanges()
    {
        $newMap = array();

        // detect new blocks
        foreach ($this->buildingBlocks as $class => $block)
        {
            if (array_key_exists($class, $this->existingBlocksMap))
            {
                // just copy over the information
                $newMap[$class] = $this->existingBlocksMap[$class];
            }
            else
            {
                // This block does not appear in the existing map. Whether to enable it or not can be decided based on its autoInstall result.
                $newMap[$class] = array(
                    'enabled' => (boolean) $block->isAutoInstall(),
                    'use_config' => true,
                    'use_assets' => true,
                );
            }
        }

        // TODO: detect removed blocks. This is actually a rare edge case since no sane person would remove a composer package without disabling its classes first. *cough*

        return $newMap;
    }

    protected function activateBlocks(array $newMap)
    {
        foreach ($newMap as $class => $settings)
        {
            $block = $this->getBlock($class);
            $block->setKernel($this->kernel);

            if (!$settings['enabled'] && $this->wasEnabled($class))
            {
                $this->disableBlock($block);
            }
            elseif ($settings['enabled'])
            {
                $this->enableBlock($block, $settings['use_config'], $settings['use_assets']);
            }
        }
    }

    /**
     *
     * @throws \InvalidArgumentException
     *
     * @param string $class
     *
     * @return BuildingBlockInterface
     */
    protected function getBlock($class)
    {
        if (!isset($this->buildingBlocks[$class]))
        {
            throw new \InvalidArgumentException("Block class $class is not registered");
        }

        return $this->buildingBlocks[$class];
    }

    /**
     * Disable a previously enabled building block.
     * TODO: ask user whether to remove class and config
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
     */
    protected function enableBlock(BuildingBlockInterface $block, $useConfig, $useAssets)
    {
        $info = $this->getBlockInfo($block);
        $this->enableBundles($info['bundle_classes']);

        if ($useConfig)
        {
            $usedModules = array();
            foreach ($info['config_templates'] as $env => $templates)
            {
                foreach ($templates as $relative => $template)
                {
                    $module = basename($template, '.yml');
                    if ($this->configHandler->checkCanCreateModuleConfig($module, $env, false))
                    {
                        $content = file_get_contents($template);
                        $this->configHandler->addModuleConfig($module, $content, $env);
                    }

                    $usedModules[$env][$module] = true;
                }
            }

            foreach ($info['default_configs'] as $env => $defaults)
            {
                foreach ($defaults as $relative => $default)
                {
                    $module = basename($default, '.yml');
                    $this->configHandler->addDefaultsImport($relative, $env);

                    if (!isset($usedModules[$env][$module]) && $this->configHandler->checkCanCreateModuleConfig($module, $env))
                    {
                        // any modules that only use a defaults file will be provided with a commented version of the given file.
                        $content = file_get_contents($default);
                        $content = "#".preg_replace("/\n/", "\n#", $content);
                        $content = "# This file was auto-generated based on ".$relative."\n# Feel free to change anything you have to.\n\n".$content;
                        $this->configHandler->addModuleConfig($module, $content, $env, true);
                    }
                }
            }
        }

        if ($useAssets)
        {
            foreach ($info['assets'] as $grouped)
            {
                foreach ($grouped as $group => $asset)
                {

                }
            }
        }
    }

    /**
     * Get all block information in a single array.
     *
     * @param BuildingBlockInterface $block
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function getBlockInfo(BuildingBlockInterface $block)
    {
        $configTemplates = array();
        $defaultConfigs = array();
        $assets = array();
        $bundleClasses = $block->getBundleClasses();

        // check all the bundle classes first before adding anything anywhere
        foreach ($bundleClasses as $bundleClass)
        {
            if (!class_exists($bundleClass))
            {
                throw new \InvalidArgumentException("Bundle class $bundleClass cannot be resolved");
            }
        }
        foreach ($this->environments as $env)
        {
            $configTemplates[$env] = $block->getConfigTemplates($env);
            $defaultConfigs[$env] = $block->getDefaultConfigs($env);
        }

        $assets = $block->getAssets();

        return array(
            'bundle_classes' => $bundleClasses,
            'config_templates' => $configTemplates,
            'default_configs' => $defaultConfigs,
            'assets' => $assets,
        );
    }

    /**
     * Add the given bundle class to AppKernel.php, no matter if it is already in there or not. The KernelManipulator will take care of this.
     *
     * @param string $bundleClass
     */
    protected function enableBundles($bundleClasses)
    {
        try
        {
            $manipulator = new KernelManipulator($this->kernel);
            $manipulator->addBundles($bundleClasses);

            $this->logger->info("Adding ".implode(', ', $bundleClasses)." to AppKernel");
        }
        catch (\RuntimeException $e)
        {
        }
    }

    /**
     * Check if the given class name was enabled in the previous configuration.
     *
     * @param string $class
     *
     * @return boolean
     */
    protected function wasEnabled($class)
    {
        return isset($this->existingBlocksMap[$class]['enabled']) && !$this->existingBlocksMap[$class]['enabled'];
    }

    /**
     * Save building block map to specific yaml config file.
     *
     * @param array $newMap
     */
    protected function saveBlocksMap(array $newMap)
    {
        $data = array(
            'c33s_construction_kit' => array(
                'building_blocks_map' => $newMap,
            ),
        );

        $content = <<<EOF
# This file is auto-updated each time c33s:construction-kit:update-blocks is called.
# This may happen automatically during various composer events (install, update, dump-autoload)
#
# Follow these rules for your maximum building experience:
#
# [*] Only edit existing block classes in this file. If you need to add another custom building block class use the
#     composer extra 'c33s-building-blocks' or register your block as a tagged service (tag 'c33s_building_block').
#     Make sure your block implements C33s\\ConstructionKitBundle\\BuildingBlock\\BuildingBlockInterface
#
# [*] You can enable or disable a full building block by simply setting the "enabled" flag to true or false, e.g.:
#     C33s\ConstructionKitBundle\BuildingBlock\ConstructionKitBuildingBlock:
#         enabled: true
#
# [*] "use_config" and "use_assets" flags will only be used if block is enabled. They do not affect disabled blocks.
#
# [*] Custom YAML comments in this file will be lost!
#

EOF;

        $content .= Yaml::dump($data, 4);

        $this->configHandler->addModuleConfig('c33s_construction_kit.map', $content, '', true);
    }
}
