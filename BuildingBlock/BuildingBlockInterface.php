<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use Symfony\Component\HttpKernel\KernelInterface;

interface BuildingBlockInterface
{
    /**
     * Return true if this block should be installed automatically as soon as it is registered (e.g. using composer).
     * This is the only public method that should not rely on a previously injected Kernel.
     *
     * @return boolean
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
     * These will be used in AppKernel.php
     *
     * @return array
     */
    public function getBundleClasses();

    /**
     * Get default config files to include automatically, grouped by environment ('default', 'dev', 'prod', ..).
     * Each environment group should return an array of bundle resource strings (@Bundle/Resources/..)
     *
     * @return array
     */
    public function getDefaultConfigs();

    /**
     * Get config.yml sections to add to the project config, grouped by environment ('default', 'dev', 'prod', ..).
     * Each environment group should return an array of bundle resource strings (@Bundle/Resources/..)
     *
     * Each section that is included in getDefaultConfigs() but not in the templates will be pre-generated using a
     * commented copy of the default config.
     *
     * @return array
     */
    public function getConfigTemplates();

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
     * The usage of this feature highly depends on your specific project structure.
     *
     * @return array
     */
    public function getAssets();

    /**
     * Return all assets for a specific asset group. If no assets are defined for the group, return empty array.
     * @see getAssets()
     *
     * @param string $groupName
     *
     * @return array
     */
    public function getAssetsByGroup($groupName);
}
