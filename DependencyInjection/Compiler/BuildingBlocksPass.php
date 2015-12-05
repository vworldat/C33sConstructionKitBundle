<?php

namespace C33s\ConstructionKitBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to add building blocks to the building block handler.
 *
 * @author Jérôme Vieilledent <lolautruche@gmail.com>
 */
class BuildingBlocksPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('c33s_construction_kit.building_block_handler')) {
            return;
        }

        $configuratorDef = $container->findDefinition('c33s_construction_kit.mapping');
        foreach (array_keys($container->findTaggedServiceIds('c33s_building_block')) as $id) {
            $configuratorDef->addMethodCall('addBuildingBlock', array(new Reference($id), $id));
        }
    }
}
