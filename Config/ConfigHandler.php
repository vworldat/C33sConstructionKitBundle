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

    protected function getDefaultsImporterFile($environment)
    {
        return $this->getBaseConfigFolder().$this->getImporterFolderName($environment).'_building_block_defaults.yml';
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
        $this->logger->debug("Initializing '$environment' config");

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

        $this->logger->debug("Found ".count($modules)." modules inside config file: ".implode(', ', array_keys($modules)));

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
     * @param boolean $allowOverwriteEmpty  Set to false to disallow replacing files without readable YAML content
     *
     * @return boolean
     */
    public function checkCanCreateModuleConfig($module, $environment = '', $allowOverwriteEmpty = true)
    {
        $targetFile = $this->getModuleFile($module, $environment);
        if (file_exists($targetFile))
        {
            $content = Yaml::parse(file_get_contents($targetFile));
            if (null !== $content || !$allowOverwriteEmpty)
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
     * @throws \RuntimeException if there is an existing config file with the same name and $overwriteExisting is false
     *
     * @param string $module
     * @param string $yamlContent
     * @param string $environment
     * @param boolean $overwriteExisting     If set to true, existing YAML files will be overwritten
     */
    public function addModuleConfig($module, $yamlContent, $environment = '', $overwriteExisting = false)
    {
        if (!$overwriteExisting && !$this->checkCanCreateModuleConfig($module, $environment))
        {
            throw new \RuntimeException("Cannot add config module '{$module}' for environment $environment because the target file already exists and contains YAML data. Please clean up manually and retry.");
        }

        $targetFile = $this->getModuleFile($module, $environment);
        if (!$overwriteExisting && file_exists($targetFile))
        {
            $this->logger->debug("File $targetFile exists, appending existing content");
            $yamlContent .= "\n".file_get_contents($targetFile);
        }

        $yamlContent = trim($yamlContent)."\n";

        $this->logger->debug("Writing $targetFile");
        file_put_contents($targetFile, $yamlContent);

        $this->enableModuleConfig($module, $environment);
    }

    /**
     * Enable the module config inside the _importer.yml file for the given environment
     *
     * @throws \RuntimeException if the file to enable importing does not exist.
     *
     * @param string $module
     * @param string $environment
     */
    public function enableModuleConfig($module, $environment = '')
    {
        $targetFile = $this->getModuleFile($module, $environment);
        if (!file_exists($targetFile))
        {
            throw new \RuntimeException("Cannot enable importer for {$module}.yml while file does not exist.");
        }

        if ($this->yamlModifier->addImportFilenameToImporterFile($this->getImporterFile($environment), $module.'.yml'))
        {
            $this->logger->info("Added module '$module' to '$environment' config importer");
        }
        else
        {
            $this->logger->debug("Module '$module' for '$environment' already exists in config importer");
        }
    }

    /**
     * Add the given file path to the _building_block_defaults importer file.
     *
     * @param string $defaultsFile
     * @param string $environment
     */
    public function addDefaultsImport($defaultsFile, $environment = '')
    {
        $defaultsImporterFile = $this->getDefaultsImporterFile($environment);
        $importerFile = $this->getImporterFile($environment);
        // register defaults importer in regular importer
        $this->yamlModifier->addImportFilenameToImporterFile($importerFile, basename($defaultsImporterFile));
        // register new remote file in defaults importer
        $this->yamlModifier->addImportFilenameToImporterFile($defaultsImporterFile, $defaultsFile);
    }

    /**
     * Add a parameter to parameters.yml and parameters.yml.dist.
     *
     * @param string $name
     * @param mixed $defaultValue
     * @param string $addComment
     */
    public function addParameter($name, $defaultValue, $addComment = null)
    {
        $this->logger->info("Setting parameter $name in parameters.yml");
        $this->yamlModifier->addParameterToFile($this->getBaseConfigFolder().'parameters.yml', $name, $defaultValue, false);
        $this->yamlModifier->addParameterToFile($this->getBaseConfigFolder().'parameters.yml.dist', $name, $defaultValue, true, $addComment);
    }
}
