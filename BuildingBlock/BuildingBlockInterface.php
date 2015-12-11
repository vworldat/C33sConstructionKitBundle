<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

interface BuildingBlockInterface
{
    /**
     * Return true if this block should be installed automatically as soon as it is registered (e.g. using composer).
     * This is the only public method that should not rely on a previously injected Kernel.
     *
     * @return bool
     */
    public function isAutoInstall();

    /**
     * Building blocks need a Kernel instance to complete most tasks, so it has to be injected whereever BuildingBlock instances are used.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel);

    /**
     * Get the fully namespaced classes of all bundles that should be enabled to use this BuildingBlock.
     * These will be used in AppKernel.php.
     *
     * @return array
     */
    public function getBundleClasses();

    /**
     * Get default config files to include automatically by environment ('default', 'dev', 'prod', ..).
     * Return an array containing full file paths indexed by bundle-notation file paths:
     * [
     *     '@MyBundle/Resources/config/defaults/my.yml' => '/path/to/my/project/src/My/MyBundle/Resources/config/defaults/my.yml',
     * ].
     *
     * @param $environment  The config environment to use ('', 'dev', 'prod', ...)
     *
     * @return array
     */
    public function getDefaultConfigs($environment = '');

    /**
     * Get sample / pre-filled config to include editable by environment ('default', 'dev', 'prod', ..).
     * Return an array containing full file paths indexed by bundle-notation file paths:
     * [
     *     '@MyBundle/Resources/config/templates/my.yml' => '/path/to/my/project/src/My/MyBundle/Resources/config/templates/my.yml',
     * ].
     *
     * Each section that is included in getDefaultConfigs() but not in the templates will be pre-generated using a
     * commented copy of the default config.
     *
     * @param $environment  The config environment to use ('', 'dev', 'prod', ...)
     *
     * @return array
     */
    public function getConfigTemplates($environment = '');

    /**
     * Return all assets that can be added automatically by this BuildingBlock. Return array grouped by asset type.
     * e.g.
     *
     * return array(
     *     "webpage_js_top" => array('path/to/jquery.js'),
     *     "webpage_js"     => array('path/to/lib1.js', 'path/to/lib2.js'),
     *     "admin_css"      => array('path/to/some.css'),
     * );
     *
     * Asset paths must be provided in a way that allows them to be loaded using Assetic.
     * The usage of this feature highly depends on your specific project structure.
     *
     * @return array
     */
    public function getAssets();

    /**
     * Return all assets for a specific asset group. If no assets are defined for the group, return empty array.
     *
     * @see getAssets()
     *
     * @param string $groupName
     *
     * @return array
     */
    public function getAssetsByGroup($groupName);

    /**
     * Get list of parameters including their default values to add to parameters.yml and parameters.yml.dist if not set already.
     *
     * This will be called every time the building blocks are refreshed.
     *
     * @return array
     */
    public function getAddParameters();

    /**
     * Get list of parameters including their default values to add to parameters.yml and parameters.yml.dist.
     * If they already exist in parameters.yml, they will be replaced.
     *
     * This will only be called once during first enabling of the building block
     *
     * @return array
     */
    public function getInitialParameters();

    /**
     * Run any arbitrary code during first-time initialization of this block.
     * Input and Output options may be used for user interaction.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function init(InputInterface $input, OutputInterface $output);
}
