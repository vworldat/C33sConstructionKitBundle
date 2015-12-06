<?php

namespace C33s\ConstructionKitBundle\Mapping;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class BuildingBlockDebugger
{
    /**
     * @var BuildingBlockMapping
     */
    protected $mapping;

    /**
     * @param string $rootDir Kernel root dir
     */
    public function __construct(BuildingBlockMapping $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * Display ConstructionKit debugging information.
     *
     * @param OutputInterface $output
     * @param array           $blockClasses
     * @param bool            $showDetails
     */
    public function debug(OutputInterface $output, array $blockClasses, $showDetails = false)
    {
        $blockClasses = $this->filterBlockClasses($blockClasses);

        $existing = $this->getMappingData();
        $updated = $this->getUpdatedMappingData();
        if ($existing === $updated) {
            $this->doDebug($existing, $output, $blockClasses, $showDetails);
        } else {
            $output->writeln("\n<comment>Blocks config needs an update. Consider running construction-kit:refresh</comment>");
            $output->writeln("\n<info>EXISTING CONFIGURATION</info>");
            $this->doDebug($existing, $output, $blockClasses, $showDetails);
            $output->writeln("\n<info>NEW CONFIGURATION AFTER construction-kit:refresh</info>");
            $this->doDebug($updated, $output, $blockClasses, $showDetails);
        }
    }

    /**
     * Get existing mapping data.
     *
     * @return array
     */
    protected function getMappingData()
    {
        return $this->mapping->getExistingMappingData();
    }

    /**
     * Get updated mapping data (the data that will be present after construction-kit:refresh has been executed).
     *
     * @return array
     */
    protected function getUpdatedMappingData()
    {
        return $this->mapping->getUpdatedMappingData();
    }

    /**
     * Filter given class names that may be incomplete to auto-fill full class names.
     *
     * @param array $blockClasses
     *
     * @return array
     */
    protected function filterBlockClasses(array $blockClasses)
    {
        $newMap = $this->getMappingData();
        $filtered = array();
        foreach ($blockClasses as $name) {
            $name = str_replace('/', '\\', $name);
            $nameLower = strtolower($name);
            $matches = array();

            foreach (array_keys($newMap['building_blocks']) as $class) {
                if ($name == $class) {
                    $filtered[$class] = $class;

                    break;
                }

                $classLower = strtolower($class);
                if (false !== strpos($classLower, $nameLower)) {
                    // the name is contained somewhere inside this class, remember for now
                    $matches[] = $class;
                }
            }

            if (0 == count($matches)) {
                throw new \InvalidArgumentException("There is no building block class matching '$name'. Did you type it correctly?");
            } elseif (count($matches) > 1) {
                throw new \InvalidArgumentException("The building block name '$name' is ambiguous and matches the following classes: '".implode("', '", $matches).'. Please be more specific.');
            }

            $filtered[] = $matches[0];
        }

        return $filtered;
    }

    /**
     * @param array           $mappingData
     * @param OutputInterface $output
     * @param array           $blockClasses
     * @param string          $showDetails
     */
    protected function doDebug(array $mappingData, OutputInterface $output, array $blockClasses = array(), $showDetails = false)
    {
        $output->writeln("\n<info>Building blocks overview</info>");
        $table = new Table($output);
        $table
            ->setHeaders(array(
                'Block class',
                'Enabled',
                'Config',
                'Assets',
                'Source',
                'Auto',
            ))
        ;

        foreach ($mappingData['building_blocks'] as $class => $config) {
            $block = $this->mapping->getBlock($class);
            $table->addRow(array(
                $class,
                $config['enabled'] ? 'Yes' : 'No',
                $config['use_config'] ? 'Yes' : 'No',
                $config['use_assets'] ? 'Yes' : 'No',
                $this->mapping->getSource($class),
                $block->isAutoInstall() ? 'Yes' : 'No',
            ));
        }

        $table->render();

        if (count($blockClasses) > 0) {
            $showDetails = true;
        }
        if ($showDetails && count($blockClasses) == 0) {
            $blockClasses = array_keys($mappingData['building_blocks']);
        }

        if ($showDetails) {
            foreach ($blockClasses as $class) {
                $this->doDebugClassDetails($output, $class);
            }
        }
    }

    /**
     * Render detailed information for the given building block class.
     *
     * @param OutputInterface $output
     * @param string          $class
     */
    protected function doDebugClassDetails(OutputInterface $output, $class)
    {
        $block = $this->mapping->getBlock($class);
        $info = $this->mapping->getBlockInfo($block);

        $output->writeln("\n<fg=cyan>{$class}:</fg=cyan>");

        $output->writeln('<info>  Bundles:</info>');
        $first = true;
        foreach ($info['bundle_classes'] as $bundle) {
            if ($first) {
                $output->writeln("    - <options=bold>{$bundle}</options=bold>");
                $first = false;
            } else {
                $output->writeln("    - $bundle");
            }
        }

        $table = new Table($output);
        $table->setStyle('compact');
        $table->addRow(array('<info> Application config:</info>', ''));
        foreach ($info['config_templates'] as $env => $templates) {
            ksort($templates);
            $configPath = ('' == $env) ? 'config' : "config<options=bold>.$env</options=bold>";
            foreach ($templates as $relative => $template) {
                $base = basename($template);
                $table->addRow(array("   - {$configPath}/{$base}", '  '.$relative));
            }
        }

        $table->addRow(array('<info> Default config:</info>', ''));
        foreach ($info['default_configs'] as $env => $defaults) {
            ksort($defaults);
            $configPath = ('' == $env) ? 'config' : "config<options=bold>.$env</options=bold>";
            foreach ($defaults as $relative => $default) {
                $base = basename($default);
                $table->addRow(array("   - {$configPath}/{$base}", '  '.$relative));
            }
        }

        $table->render();

        $output->writeln('<info>  Assets:</info>');
        foreach ($info['assets'] as $group => $grouped) {
            $output->writeln("    <comment>{$group}</comment>", '');
            foreach ($grouped as $asset) {
                $output->writeln("      - {$asset}");
            }
        }
    }
}
