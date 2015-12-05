<?php

namespace C33s\ConstructionKitBundle\Mapping;

use C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockInterface;
use C33s\ConstructionKitBundle\Exception\InvalidBlockClassException;
use C33s\SymfonyConfigManipulatorBundle\Manipulator\ConfigManipulator;
use C33s\ConstructionKitBundle\Exception\InvalidBundleClassException;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Psr\Log\LoggerInterface;

class BuildingBlockMapping
{
    /**
     * @var array
     */
    protected $existingMappingData;

    /**
     * @var array
     */
    protected $newMappingData;

    /**
     * @var BuildingBlockInterface[]
     */
    protected $buildingBlocks = array();

    /**
     * @var array
     */
    protected $buildingBlockSources = array();

    /**
     * @var ConfigManipulator
     */
    protected $configManipulator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        array $existingMappingData,
        array $composerBlockClasses,
        ConfigManipulator $configManipulator,
        LoggerInterface $logger
    ) {
        $this->existingMappingData = $existingMappingData;
        $this->configManipulator = $configManipulator;
        $this->logger = $logger;

        $this->loadComposerBuildingBlocks($composerBlockClasses);
    }

    /**
     * Get current mapping without detecting any changes.
     *
     * @return array()
     */
    public function getExistingMappingData()
    {
        return $this->existingMappingData;
    }

    /**
     * Detect mapping changes and return mapping data.
     *
     * @return array()
     */
    public function getUpdatedMappingData()
    {
        if (null === $this->newMappingData) {
            $this->newMappingData = $this->detectChanges();
        }

        return $this->newMappingData;
    }

    /**
     * Get a block definition by its class.
     *
     * @throws InvalidBlockClassException
     *
     * @param string $class
     * @return BuildingBlockInterface
     */
    public function getBlock($class)
    {
        if (!isset($this->buildingBlocks[$class])) {
            throw new InvalidBlockClassException("Block class $class is not registered");
        }

        return $this->buildingBlocks[$class];
    }

    /**
     * Get the source for the given block class.
     *
     * @throws InvalidBlockClassException
     *
     * @param string $class
     * @return string
     */
    public function getSource($class)
    {
        if (!isset($this->buildingBlockSources[$class])) {
            throw new InvalidBlockClassException("Block class $class is not registered");
        }

        return $this->buildingBlockSources[$class];
    }

    /**
     * Get all block information for the given block in a single array.
     *
     * @throws InvalidBundleClassException if block provides invalid bundle names
     *
     * @param BuildingBlockInterface $block
     * @return array
     */
    public function getBlockInfo(BuildingBlockInterface $block)
    {
        $configTemplates = array();
        $defaultConfigs = array();
        $assets = array();
        $bundleClasses = $block->getBundleClasses();

        // check all the bundle classes first before adding anything anywhere
        foreach ($bundleClasses as $bundleClass) {
            $this->checkBundleClass($bundleClass);
        }
        foreach ($this->configManipulator->getEnvironments() as $env) {
            $configTemplates[$env] = $block->getConfigTemplates($env);
            $defaultConfigs[$env] = $block->getDefaultConfigs($env);
        }

        $assets = $block->getAssets();

        return array(
            'class' => get_class($block),
            'bundle_classes' => $bundleClasses,
            'config_templates' => $configTemplates,
            'default_configs' => $defaultConfigs,
            'assets' => $assets,
        );
    }

    /**
     * Check if the given class name is a valid (and available) Symfony bundle.
     *
     * @throws InvalidBundleClassException
     * @param string $bundleClass
     */
    protected function checkBundleClass($bundleClass)
    {
        if (!class_exists($bundleClass)) {
            throw new InvalidBundleClassException("Bundle class $bundleClass cannot be resolved");
        }

        $interfaces = class_implements($bundleClass);
        if (!isset($interfaces['Symfony\Component\HttpKernel\Bundle\BundleInterface'])) {
            throw new InvalidBundleClassException("Bundle class $bundleClass does not implement BundleInterface");
        }
    }

    /**
     * Set a building block definition.
     *
     * @param BuildingBlockInterface $block
     * @param string                 $setBy Optional information where this block comes from (for debugging)
     */
    public function addBuildingBlock(BuildingBlockInterface $block, $setBy = '')
    {
        $class = get_class($block);
        $this->buildingBlocks[$class] = $block;
        $this->buildingBlockSources[$class] = $setBy;
    }

    /**
     * Load BuildingBlocks defined by composer.
     *
     * @param array $composerBuildingBlockClasses
     * @return BuildingBlockInterface[]
     */
    protected function loadComposerBuildingBlocks($composerBuildingBlockClasses)
    {
        foreach ($composerBuildingBlockClasses as $package => $classes) {
            foreach ($classes as $class) {
                if (0 !== strpos($class, '\\')) {
                    // make sure the class always starts with a backslash to reduce danger of having duplicate classes
                    $class = '\\'.$class;
                }

                if (!class_exists($class)) {
                    throw new \InvalidArgumentException("The building block class '$class' defined by composer package '$package' does not exist or cannot be accessed.");
                }
                if (!array_key_exists('C33s\\ConstructionKitBundle\\BuildingBlock\\BuildingBlockInterface', class_implements($class))) {
                    throw new \InvalidArgumentException("The building block class '$class' defined by composer package '$package' does not implement BuildingBlockInterface.");
                }

                $this->addBuildingBlock(new $class(), $package);
            }
        }
    }

    /**
     * At this point all available blocks are collected inside $this->buildingBlocks. The existing class map is placed in $this->existingMapping.
     * We have to detect both new and removed blocks and act accordingly.
     *
     * @return array New blocks map
     */
    protected function detectChanges()
    {
        $newMap = array('building_blocks' => array());

        if (!array_key_exists('building_blocks', $this->existingMappingData)) {
            $this->existingMappingData['building_blocks'] = array();
        }
        if (!array_key_exists('assets', $this->existingMappingData)) {
            $this->existingMappingData['assets'] = array();
        }

        // detect new blocks
        foreach ($this->buildingBlocks as $class => $block) {
            if (array_key_exists($class, $this->existingMappingData['building_blocks'])) {
                // just copy over the information
                $newMap['building_blocks'][$class] = $this->existingMappingData['building_blocks'][$class];
            } else {
                // This block does not appear in the existing map. Whether to enable it or not can be decided based on its autoInstall result.
                $newMap['building_blocks'][$class] = array(
                    'enabled' => (boolean) $block->isAutoInstall(),
                    'init' => true,
                    'use_config' => true,
                    'use_assets' => true,
                );
            }
        }

        ksort($newMap['building_blocks']);
        $newMap['assets'] = $this->existingMappingData['assets'];

        foreach ($newMap['building_blocks'] as $class => $settings) {
            $useAssets = $settings['enabled'] && $settings['use_assets'];

            $block = $this->getBlock($class);
            try {
                $this->getBlockInfo($block);
            } catch (InvalidBundleClassException $e) {
                $this->logger->warning('Error loading building block information for '.$class.': '.$e->getMessage());
                continue;
            }

            $assets = $block->getAssets();

            foreach ($assets as $group => $grouped) {
                if (!array_key_exists($group, $newMap['assets'])) {
                    $newMap['assets'][$group] = array(
                        'enabled' => array(),
                        'disabled' => array(),
                        'filters' => array(),
                    );
                }

                foreach ($grouped as $asset) {
                    if ($useAssets && !in_array($asset, $newMap['assets'][$group]['enabled']) && !in_array($asset, $newMap['assets'][$group]['disabled'])) {
                        // append assets that did not appear previously
                        $newMap['assets'][$group]['enabled'][] = $asset;
                    } elseif (!$useAssets && in_array($asset, $newMap['assets'][$group]['enabled'])) {
                        // disable previously defined assets if enabled
                        $key = array_search($asset, $newMap['assets'][$group]['enabled']);
                        unset($newMap['assets'][$group]['enabled'][$key]);
                        $newMap['assets'][$group]['enabled'] = array_values($newMap['assets'][$group]['enabled']);
                        $newMap['assets'][$group]['disabled'][] = $asset;
                    }
                }
            }
        }

        // TODO: detect removed blocks. This is actually a rare edge case since no sane person would remove a composer package without disabling its classes first. *cough*

        return $newMap;
    }

}
