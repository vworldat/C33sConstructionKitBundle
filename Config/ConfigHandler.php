<?php

namespace C33s\ConstructionKitBundle\Config;

use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * This class is used to handle the Symfony config files in a more structured way.
 *
 */
class ConfigHandler
{
    /**
     *
     * @var string
     */
    protected $kernelRootDir;

    /**
     *
     * @var LoggerInterface
     */
    protected $logger;

    protected $environments = array(
        '',
        'dev',
        'prod',
        'test',
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
    public function __construct($kernelRootDir, LoggerInterface $logger)
    {
        $this->kernelRootDir = $kernelRootDir;
        $this->logger = $logger;

        $this->yamlModifier = new YamlModifier();
    }

    public function addEnvironment($environment)
    {
        if (!in_array($environment, $this->environments))
        {
            $this->environments[] = $environment;
        }
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

    protected function getConfigFile($environment)
    {
        return $this->getBaseConfigFolder().rtrim('config_'.$environment, '_').'.yml';
    }

    protected function getImporterFolderName($environment)
    {
        return rtrim('config.'.$environment, '.').'/';
    }

    protected function getImporterFile($environment)
    {
        return $this->getBaseConfigFolder().$this->getImporterFolderName($environment).'_importer.yml';
    }

    public function getModuleFile($module, $environment = '')
    {
        return $this->getBaseConfigFolder().$this->getImporterFolderName($environment).$module.'.yml';
    }

    protected function initConfigs()
    {
        if ($this->isInitialized)
        {
            return;
        }

        $this->logger->info("Checking and initializing config files");
        foreach ($this->environments as $environment)
        {
            $this->initConfig($environment);
        }

        $this->isInitialized = true;
    }

    protected function initConfig($environment)
    {
        $this->logger->debug("Initializing $environment config");

        $configFile = $this->getConfigFile($environment);
        if (!file_exists($configFile))
        {
            $this->logger->warning("Could not find $configFile");

            return;
        }

        $folderName = $this->getImporterFolderName($environment);
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

            if (!$this->checkCanCreateModuleConfig($module, $environment))
            {
                throw new \RuntimeException("Cannot move config module '{$module}' from file config/config_{$environment}.yml to file config/{$folderName}{$module}.yml because it already exists and contains YAML data. Please clean up manually and retry.");
            }
        }

        $this->logger->debug("Adding modules to separated config files");
        foreach ($modules as $module => $data)
        {
            if ('imports' == $module)
            {
                continue;
            }

            $this->addModuleConfig($module, $data['content'], $environment);
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
     * Check if the config for the given module name and environment can safely be created.
     *
     * @param string $module
     * @param string $environment
     */
    protected function checkCanCreateModuleConfig($module, $environment = '')
    {
        $targetFile = $this->getModuleFile($module, $environment);
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
     * Add the given content as {$module}.yml into the config folder for the given environment.
     *
     * @param string $module
     * @param string $yamlContent
     * @param string $environment
     */
    public function addModuleConfig($module, $yamlContent, $environment = '')
    {
        if (!$this->checkCanCreateModuleConfig($module, $environment))
        {
            throw new \RuntimeException("Cannot move config module '{$module}' for environment $environment because the target file already exists and contains YAML data. Please clean up manually and retry.");
        }

        $this->logger->debug("Adding module $module to $environment config importer");
        $this->yamlModifier->addImportFilenameToImporterFile($this->getImporterFile($environment), $module.'.yml');

        $targetFile = $this->getModuleFile($module, $environment);
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
