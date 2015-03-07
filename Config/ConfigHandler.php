<?php

namespace C33s\ConstructionKitBundle\Config;

use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This class is used to handle the Symfony config files in a more structured way.
 *
 */
class ConfigHandler implements LoggerAwareInterface
{
    /**
     *
     * @var string
     */
    protected $kernelRootDir;

    protected $logger;

    protected $suffixes = array(
        '',
        '_dev',
        '_prod',
        '_test',
    );

    protected $filesToWrite = array();

    protected $isInitialized = false;

    /**
     *
     * @var YamlModifier
     */
    protected $yamlModifier;

    /**
     *
     * @param string $rootDir   Kernel root dir
     */
    public function __construct($kernelRootDir)
    {
        $this->kernelRootDir = $kernelRootDir;
        $this->yamlModifier = new YamlModifier();
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Refresh Symfony config files, moving all config sections inside the main config*.yml files into separated sub files.
     * e.g.: "framework:" inside app/config/config_dev.yml will be moved into app/config/config.dev/framework.yml
     */
    public function refresh()
    {
        $this->initConfigs();
    }

    protected function getBaseConfigFolder()
    {
        return $this->kernelRootDir.'/config/';
    }

    protected function getImporterFolderName($suffix)
    {
        return 'config'.str_replace('_', '.', $suffix).'/';
    }

    protected function getImporterFile($suffix)
    {
        return $this->getBaseConfigFolder().$this->getImporterFolderName($suffix).'_importer.yml';
    }

    public function getModuleFile($module, $suffix = '')
    {
        return $this->getBaseConfigFolder().$this->getImporterFolderName($suffix).$module.'.yml';
    }

    protected function initConfigs()
    {
        if ($this->isInitialized)
        {
            return;
        }

        $this->logger->info("Checking and initializing config files");
        foreach ($this->suffixes as $suffix)
        {
            $this->initConfig($suffix);
        }

        $this->isInitialized = true;
    }

    protected function initConfig($suffix)
    {
        $this->logger->debug("Initializing $suffix config");

        $configFile = $this->kernelRootDir.'/config/config'.$suffix.'.yml';
        if (!file_exists($configFile))
        {
            $this->logger->warning("Could not find $configFile");

            return;
        }

        $folderName = $this->getImporterFolderName($suffix);
        $folder = $this->getBaseConfigFolder().$folderName;
        if (!is_dir($folder))
        {
            $this->logger->info("Creating folder config/{$folderName}");
            mkdir($folder);
        }

        $modules = $this->yamlModifier->parseYamlModules($configFile);

        $this->logger->debug("Checking modules");
        // check if we can safely move all the config. This has to be done first to make sure the Symfony config does not break.
        foreach ($modules as $module => $data)
        {
            if ('imports' == $module)
            {
                continue;
            }

            if (!$this->checkCanCreateModuleConfig($module, $suffix))
            {
                throw new \RuntimeException("Cannot move config module '{$module}' from file config/config{$suffix}.yml to file config/{$folderName}{$module}.yml because it already exists and contains YAML data. Please clean up manually and retry.");
            }
        }

        $this->logger->debug("Adding modules to separated config files");
        foreach ($modules as $module => $data)
        {
            if ('imports' == $module)
            {
                continue;
            }

            $this->addModuleConfig($module, $data['content'], $suffix);
        }

        $data = array(
            'imports' => isset($modules['imports']) ? $modules['imports']['data']['imports'] : array(),
        );

        $filename = $folderName.'_importer.yml';
        if (!$this->yamlModifier->dataContainsImportFile($data, $filename))
        {
            $data['imports'][] = array('resource' => $filename);
        }

        $this->logger->debug("Re-writing $configFile");
        file_put_contents($configFile, Yaml::dump($data));
    }

    /**
     * Check if the config for the given module name and suffix can safely be created.
     *
     * @param string $module
     * @param string $suffix
     */
    protected function checkCanCreateModuleConfig($module, $suffix = '')
    {
        $targetFile = $this->getModuleFile($module, $suffix);
        if (file_exists($targetFile))
        {
            $content = Yaml::parse(file_get_contents($targetFile));
            if (null !== $content)
            {
                // there is something inside this file that parses as yaml
                return false;
            }
        }

        return true;
    }

    /**
     * Add the given content as {$module}.yml into the config folder for the given suffix.
     *
     * @param string $module
     * @param string $yamlContent
     * @param string $suffix
     */
    public function addModuleConfig($module, $yamlContent, $suffix = '')
    {
        if (!$this->checkCanCreateModuleConfig($module, $suffix))
        {
            throw new \RuntimeException("Cannot move config module '{$module}' for suffix $suffix because the target file already exists and contains YAML data. Please clean up manually and retry.");
        }

        $this->logger->debug("Adding module $module to $suffix config importer");
        $this->yamlModifier->addImportFilenameToImporterFile($this->getImporterFile($suffix), $module.'.yml');

        $targetFile = $this->getModuleFile($module, $suffix);
        if (file_exists($targetFile))
        {
            $this->logger->debug("File $targetFile exists, appending existing content");
            $yamlContent .= "\n".file_get_contents($targetFile);
        }

        $yamlContent = trim($yamlContent)."\n";

        $this->logger->debug("Writing $targetFile");
        file_put_contents($targetFile, $yamlContent);
    }
}
