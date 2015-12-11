<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

abstract class SimpleBuildingBlock implements BuildingBlockInterface
{
    /**
     * @var KernelInterface
     */
    protected $kernel;

    /**
     * Instance of main bundle to use for resources.
     *
     * @var string
     */
    protected $mainBundle;

    /**
     * Holds default config files once detected.
     *
     * @var array
     */
    protected $defaultConfigs = array();

    /**
     * Holds config template files once detected.
     *
     * @var array
     */
    protected $configTemplates = array();

    /**
     * Holds asset files once detected.
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
        if (null === $this->kernel) {
            throw new \LogicException('Please inject a KernelInterface instance using setKernel() before using this method.');
        }

        return $this->kernel;
    }

    /**
     * Return true if this block should be installed automatically as soon as it is registered (e.g. using composer).
     * This is the only public method that should not rely on a previously injected Kernel.
     *
     * @return bool
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
     * @return BundleInterface
     */
    protected function getMainBundle()
    {
        if (null !== $this->mainBundle) {
            return $this->mainBundle;
        }

        $classes = $this->getBundleClasses();
        if (!count($classes)) {
            throw new \RuntimeException('SimpleBuildingBlock requires you to at least define one bundle in getBundleClasses() to use as your main bundle');
        }

        $class = reset($classes);
        $this->mainBundle = new $class();

        return $this->mainBundle;
    }

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
    public function getDefaultConfigs($environment = '')
    {
        if (!isset($this->defaultConfigs[$environment])) {
            $env = ('' === $environment) ? $environment : '_'.$environment;
            $this->defaultConfigs[$environment] = $this->findFilesInBundleDir('Resources/config/defaults'.$env.'/'.$this->getPathSuffix().'/', '*.yml');
        }

        return $this->defaultConfigs[$environment];
    }

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
    public function getConfigTemplates($environment = '')
    {
        if (!isset($this->configTemplates[$environment])) {
            $env = '' === $environment ? $environment : '_'.$environment;
            $this->configTemplates[$environment] = $this->findFilesInBundleDir('Resources/config/templates'.$env.'/'.$this->getPathSuffix().'/', '*.yml');
        }

        return $this->configTemplates[$environment];
    }

    /**
     * Find all yml files in the given folder in this block's main bundle.
     *
     * @param string $dirName
     * @param string $pattern File name pattern to search for
     *
     * @return array
     */
    protected function findFilesInBundleDir($dirName, $pattern, $depth = 0)
    {
        $baseDir = $this->getMainBundleDir();
        $bundleName = $this->getMainBundle()->getName();

        $dir = $baseDir.'/'.$dirName;
        if (!is_dir($dir)) {
            return array();
        }

        $dir = realpath($dir);

        $finder = Finder::create()
            ->files()
            ->name($pattern)
            ->depth($depth)
            ->in($dir)
        ;

        $files = array();
        foreach ($finder as $file) {
            /* @var $file SplFileInfo */
            $relative = substr($file->getRealPath(), strlen($baseDir));
            $files['@'.$bundleName.$relative] = $file->getRealPath();
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
     * Asset paths must be provided in a way that allows them to be loaded using Assetic.
     * The usage of this feature highly depends on your specific project structure.
     *
     * @return array
     */
    public function getAssets()
    {
        if (null === $this->assets) {
            $this->assets = $this->findAssets();
        }

        return $this->assets;
    }

    /**
     * Find assets in Resources/public and Resources/private + path suffix.
     *
     * @return array
     */
    protected function findAssets()
    {
        $searchIn = array(
            'Resources/public/'.$this->getPathSuffix().'/',
            'Resources/private/'.$this->getPathSuffix().'/',
        );

        $baseDir = $this->getMainBundleDir();
        $assets = array();
        foreach ($searchIn as $searchDir) {
            $dir = $baseDir.'/'.$searchDir;
            if (!is_dir($dir)) {
                continue;
            }

            $finder = Finder::create()
                ->directories()
                ->depth(0)
                ->ignoreDotFiles(true)
                ->in($dir)
            ;

            foreach ($finder as $groupDir) {
                $files = $this->findFilesInBundleDir($searchDir.'/'.$groupDir->getFilename(), '*.*', '< 10');
                if (count($files)) {
                    // for assets we only use the @Bundle relative notation to be used with assetic
                    $assets[$groupDir->getFilename()] = array_keys($files);
                }
            }
        }

        return $assets;
    }

    /**
     * Get the directory containing the main bundle's class file.
     * If not overwritten, reflection is used to determine this.
     *
     * @return string
     */
    protected function getMainBundleDir()
    {
        return $this->getMainBundle()->getPath();
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
     *
     * @see getAssets()
     *
     * @param string $groupName
     *
     * @return array
     */
    public function getAssetsByGroup($groupName)
    {
        $assets = $this->getAssets();
        if (array_key_exists($groupName, $assets)) {
            return $assets[$groupName];
        }

        return array();
    }

    /**
     * Get list of parameters including their default values to add to parameters.yml and parameters.yml.dist if not set already.
     *
     * This will be called every time the building blocks are refreshed.
     *
     * @return array
     */
    public function getAddParameters()
    {
        return array();
    }

    /**
     * Get list of parameters including their default values to add to parameters.yml and parameters.yml.dist.
     * If they already exist in parameters.yml, they will be replaced.
     *
     * This will only be called once during first enabling of the building block
     *
     * @return array
     */
    public function getInitialParameters()
    {
        return array();
    }

    /**
     * Run any arbitrary code during first-time initialization of this block.
     * Input and Output options may be used for user interaction.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    public function init(InputInterface $input, OutputInterface $output)
    {
    }
}
