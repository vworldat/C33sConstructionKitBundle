<?php

namespace C33s\ConstructionKitBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('c33s_construction_kit');

        $rootNode
            ->fixXmlConfig('config_environment')
            ->children()
                ->arrayNode('config_environments')
                    ->prototype('scalar')
                    ->end()
                    ->defaultValue(array('', 'dev', 'prod', 'test'))
                ->end()
                ->arrayNode('composer_building_blocks')
                    ->defaultValue(array())
                    ->useAttributeAsKey('package')
                    // required because otherwise symfony replaces dashes in package names with underscores
                    ->normalizeKeys(false)
                    ->prototype('array')
                        ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('building_blocks_map')
                    ->useAttributeAsKey('class')
                    ->prototype('array')
                        ->canBeEnabled()
                        ->children()
                            ->booleanNode('use_config')
                                ->defaultValue(true)
                            ->end()
                            ->booleanNode('use_assets')
                                ->defaultValue(true)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('asset_map')
                    ->useAttributeAsKey('group')
                    ->prototype('array')
                        ->prototype('scalar')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
