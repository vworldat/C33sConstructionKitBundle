<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use C33s\ConstructionKitBundle\Config\ConfigHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

class BuildingBlockHandler
{
    /**
     *
     * @var string
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

    /**
     *
     * @param string $rootDir   Kernel root dir
     */
    public function __construct(KernelInterface $kernel, LoggerInterface $logger, ConfigHandler $configHandler, $composerBuildingBlocks, $blocksMap)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->configHandler = $configHandler;
        $this->composerBuildingBlockClasses = $composerBuildingBlocks;
        $this->existingBlocksMap = $blocksMap;
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
        $this->saveMap($newMap);
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

            if (!$settings['enabled'])
            {
                if (isset($this->existingBlocksMap[$class]['enabled']) && !$this->existingBlocksMap[$class]['enabled'])
                {
                    $this->disableBlock($block);
                }
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

    protected function saveMap(array $newMap)
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
