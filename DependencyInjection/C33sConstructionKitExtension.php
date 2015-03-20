<?php

namespace C33s\ConstructionKitBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class C33sConstructionKitExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('construction-kit.yml');

        $container->setParameter('c33s_construction_kit.building_blocks.composer', $config['composer_building_blocks']);
        $container->setParameter('c33s_construction_kit.mapping', $config['mapping']);
        $container->setParameter('c33s_construction_kit.environments', $config['config_environments']);
    }
}
