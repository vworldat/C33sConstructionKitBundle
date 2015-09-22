<?php

namespace C33s\ConstructionKitBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class C33sConstructionKitExtension extends Extension implements PrependExtensionInterface
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
        $container->setParameter('c33s_construction_kit.mapping', isset($config['mapping']) ? $config['mapping'] : array());
        $container->setParameter('c33s_construction_kit.environments', $config['config_environments']);
    }

    public function prepend(ContainerBuilder $container)
    {
        $this->prependAssets($container);
    }

    private function prependAssets(ContainerBuilder $container)
    {
        if (!$container->hasExtension('assetic')) {
            die('noassetic');

            return;
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration(new Configuration(), $configs);
        if (!isset($config['mapping']['assets']) || empty($config['mapping']['assets'])) {
            return;
        }

        $path = $config['assets_output_prefix'];
        $assetMap = array();
        foreach ($config['mapping']['assets'] as $group => $grouped) {
            if (empty($grouped['enabled'])) {
                continue;
            }

            $assetMap[$group] = array(
                'combine' => true,
                'output' => "{$path}__{$group}__",
                'inputs' => $grouped['enabled'],
                'filters' => $grouped['filters'],
            );
        }

        if (empty($assetMap)) {
            return;
        }

        $container->prependExtensionConfig('assetic', array('assets' => $assetMap));
    }
}
