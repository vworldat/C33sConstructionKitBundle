<?php

namespace C33s\ConstructionKitBundle\Mapping;

use C33s\ConstructionKitBundle\BuildingBlock\BuildingBlockInterface;
use C33s\ConstructionKitBundle\Exception\InvalidBlockClassException;
use C33s\ConstructionKitBundle\Exception\InvalidBundleClassException;
use C33s\SymfonyConfigManipulatorBundle\Manipulator\ConfigManipulator;
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

    /**
     * @param array             $existingMappingData
     * @param array             $composerBlockClasses
     * @param ConfigManipulator $configManipulator
     * @param LoggerInterface   $logger
     */
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
     *
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
     *
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
     *
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
     *
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

        if (!isset($this->existingMappingData['building_blocks'])) {
            $this->existingMappingData['building_blocks'] = array();
        }
        if (!isset($this->existingMappingData['assets'])) {
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
                    'force_init' => true,
                    'use_config' => true,
                    'use_assets' => true,
                );
            }
        }

        ksort($newMap['building_blocks']);
        $newMap['assets'] = $this->existingMappingData['assets'];

        foreach ($newMap['building_blocks'] as $class => $settings) {
            $block = $this->getBlock($class);
            try {
                // trying to load block just to make sure invalid blocks are not being handled later on
                $this->getBlockInfo($block);
            } catch (InvalidBundleClassException $e) {
                $this->logger->warning('Error loading building block information for '.$class.': '.$e->getMessage());
                continue;
            }

            $useAssets = $settings['enabled'] && $settings['use_assets'];
            $newMap['assets'] = $this->organizeAssets($block, $newMap['assets'], $useAssets);
        }

        return $newMap;
    }

    /**
     * Update assets for the given block. New assets (that did not appear previously) will be enabled
     * if assets for the given block are enabled ($useAssets is true).
     *
     * @param BuildingBlockInterface $block
     * @param array                  $assetsData
     * @param bool                   $useAssets
     */
    protected function organizeAssets(BuildingBlockInterface $block, array $assetsData, $useAssets)
    {
        $assets = $block->getAssets();

        foreach ($assets as $group => $grouped) {
            if (array_key_exists($group, $assetsData)) {
                $groupData = $assetsData[$group];
            } else {
                $groupData = array(
                    'enabled' => array(),
                    'disabled' => array(),
                    'filters' => array(),
                );
            }

            foreach ($grouped as $asset) {
                if ($useAssets && !in_array($asset, $groupData['enabled']) && !in_array($asset, $groupData['disabled'])) {
                    // append assets that did not appear previously
                    $groupData['enabled'][] = $asset;
                } elseif (!$useAssets && in_array($asset, $groupData['enabled'])) {
                    // disable previously defined assets if enabled
                    $key = array_search($asset, $groupData['enabled']);
                    unset($groupData['enabled'][$key]);
                    $groupData['enabled'] = array_values($groupData['enabled']);
                    $groupData['disabled'][] = $asset;
                }
            }

            $assetsData[$group] = $groupData;
        }

        return $assetsData;
    }
}
