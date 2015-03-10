<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\SplFileInfo;

abstract class SimpleBuildingBlock implements BuildingBlockInterface
{
    /**
     *
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * Name of main bundle to use for resources.
     *
     * @var string
     */
    protected $mainBundle;

    /**
     * Holds default config files once detected
     *
     * @var array
     */
    protected $defaultConfigs;

    /**
     * Holds config template files once detected
     *
     * @var array
     */
    protected $configTemplates;

    /**
     * Holds asset files once detected
     *
     * @var array
     */
    protected $assets;

    /**
     * Building blocks need a Kernel instance to complete most tasks.
     *
     * @param KernelInterface $kernel
     */
    public function setKernel(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return KernelInterface
     */
    protected function getKernel()
    {
        if (null === $this->kernel)
        {
            throw new \LogicException('Please inject a KernelInterface instance using setKernel() before using this method.');
        }

        return $this->kernel;
    }

    /**
     * Return true if this block should be installed automatically as soon as it is registered (e.g. using composer).
     * This is the only public method that should not rely on a previously injected Kernel.
     *
     * @return boolean
     */
    public function isAutoInstall()
    {
        return false;
    }

    /**
     * Get the bundle holding resources used by this block.
     * By default the first bundle from the list of bundles to activate is used.
     *
     * Does not rely on setKernel()
     *
     * @return string  The bundle name (e.g. "C33sConstructionKitBundle")
     */
    protected function getMainBundle()
    {
        if (null !== $this->mainBundle)
        {
            return $this->mainBundle;
        }

        if (!count($this->getBundleClasses()))
        {
            throw new \RuntimeException('SimpleBuildingBlock requires you to at least define one bundle in getBundleClasses() to use as your main bundle');
        }

        $class = reset($this->getBundleClasses());
        $pos = strrpos($class, '\\');

        return $this->mainBundle = (false === $pos) ? $class : substr($class, $pos + 1);
    }

    /**
     * Get default config files to include automatically, grouped by environment ('default', 'dev', 'prod', ..).
     * Each environment group should return an array of bundle resource strings (@Bundle/Resources/..)
     *
     * @return array
    */
    public function getDefaultConfigs()
    {
        if (null === $this->defaultConfigs)
        {
            $this->defaultConfigs = $this->findFilesInBundleDir('Resources/config/defaults/'.$this->getPathSuffix().'/', '*.yml');
        }

        return $this->defaultConfigs;
    }

    /**
     * Get config.yml sections to add to the project config, grouped by environment ('default', 'dev', 'prod', ..).
     * Each environment group should return an array of bundle resource strings (@Bundle/Resources/..)
     *
     * Each section that is included in getDefaultConfigs() but not in the templates will be pre-generated using a
     * commented copy of the default config.
     *
     * @return array
     */
    public function getConfigTemplates()
    {
        if (null === $this->configTemplates)
        {
            $this->configTemplates = $this->findFilesInBundleDir('Resources/config/templates/'.$this->getPathSuffix().'/', '*.yml');
        }

        return $this->configTemplates;
    }

    /**
     * Find all yml files in the given folder in this block's main bundle.
     *
     * @param string $dirName
     * @param string $pattern           File name pattern to search for
     *
     * @return array
     */
    protected function findFilesInBundleDir($dirName, $pattern, $depth = 0)
    {
        try
        {
            $dir = $this->getKernel()->locateResource('@'.$this->getMainBundle().'/'.$dirName);
        }
        catch (\InvalidArgumentException $e)
        {
            return array();
        }

        $finder = Finder::create()
            ->files()
            ->name($pattern)
            ->depth($depth)
            ->in($dir)
        ;

        $files = array();
        foreach ($finder as $file)
        {
            /* @var $file SplFileInfo */
            $files[] = '@'.$this->getMainBundle().'/'.$dirName.'/'.$file->getFilename();
        }

        return $files;
    }

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
    public function getAssets()
    {
        if (null === $this->assets)
        {
            $this->assets = $this->findAssets();
        }

        return $this->assets;
    }

    /**
     * Find assets in Resources/public and Resources/non-public + path suffix.
     *
     * @return array
     */
    protected function findAssets()
    {
        $searchIn = array(
            'Resources/public/'.$this->getPathSuffix().'/',
            'Resources/non-public/'.$this->getPathSuffix().'/',
        );

        $baseDir = $this->getKernel()->locateResource('@'.$this->getMainBundle());
        $assets = array();
        foreach ($searchIn as $searchDir)
        {
            $dir = $baseDir.'/'.$searchDir;
            if (!is_dir($dir))
            {
                continue;
            }

            $finder = Finder::create()
                ->directories()
                ->depth(0)
                ->ignoreDotFiles(true)
                ->in($dir)
            ;

            foreach ($finder as $groupDir)
            {
                $files = $this->findFilesInBundleDir($searchDir.'/'.$groupDir->getFilename(), '*.*', '< 10');
                if (count($files))
                {
                    $assets[$groupDir->getFilename()] = $files;
                }
            }
        }

        return $assets;
    }

    /**
     * This suffix will be added to all searches for default configs, config templates and assets.
     * Use this to easily have 1 bundle serve multiple building blocks without them interfering.
     *
     * @return string
     */
    protected function getPathSuffix()
    {
        return '';
    }

    /**
     * Return all assets for a specific asset group. If no assets are defined for the group, return empty array.
     * @see getAssets()
     *
     * @param string $groupName
     *
     * @return array
    */
    public function getAssetsByGroup($groupName)
    {
        $assets = $this->getAssets();
        if (array_key_exists($groupName, $assets))
        {
            return $assets[$groupName];
        }

        return array();
    }
}
