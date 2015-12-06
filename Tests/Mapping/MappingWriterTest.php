<?php

namespace C33s\ConstructionKitBundle\Tests\Functional;

use C33s\ConstructionKitBundle\DependencyInjection\C33sConstructionKitExtension;
use C33s\ConstructionKitBundle\Mapping\BuildingBlockMapping;
use C33s\ConstructionKitBundle\Mapping\MappingWriter;
use C33s\ConstructionKitBundle\Tests\BaseTestCase;
use C33s\SymfonyConfigManipulatorBundle\Manipulator\ConfigManipulator;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;

class MappingWriterTest extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->setupTempDir();
    }

    protected function tearDown()
    {
        $this->cleanupTempDir();
        parent::tearDown();
    }

    /**
     * @dataProvider provideRefreshFolders
     *
     * @param string $sourceDir
     */
    public function testRefreshBlocksHandlesFiles($sourceDir, $namespace)
    {
        $this->mirrorDirectory($sourceDir.'/source', $this->tempDir);

        require_once $this->tempDir.'/AppKernel.php';
        $kernelClass = $namespace.'\AppKernel';
        $kernel = new $kernelClass('dev', false);

        $writer = $this->getMappingWriter($kernel);

        $output = new NullOutput();
        $writer->refresh($output);

        $this->assertDirectoriesAreEqual($sourceDir.'/expected', $this->tempDir);
    }

    public function provideRefreshFolders()
    {
        return array(
            // testing a plain Symfony standard edition 2.7.3, first command run
            array(__DIR__.'/../Fixtures/refresh-blocks/symfony-standard-2.7.3-first-run', 'SymfonyStandardFirstRun'),

            // testing a plain Symfony standard edition 2.7.3, second command run
            array(__DIR__.'/../Fixtures/refresh-blocks/symfony-standard-2.7.3-second-run', 'SymfonyStandardSecondRun'),

            // testing with default construction-kit and config-manipulator blocks provided by composer config
            array(__DIR__.'/../Fixtures/refresh-blocks/adding-composer-blocks', 'AddingComposerBlocks'),
        );
    }

    /**
     * @param string $kernelRootDir
     *
     * @return MappingWriter
     */
    protected function getMappingWriter(Kernel $kernel)
    {
        $logger = new NullLogger();

        // simulate symfony container loading configuration
        $configs = array();
        foreach (array('c33s_construction_kit.composer.yml', 'c33s_construction_kit.map.yml') as $file) {
            $path = $kernel->getRootDir().'/config/config/'.$file;
            if (file_exists($path)) {
                $config = Yaml::parse(file_get_contents($path));
                $configs[] = $config['c33s_construction_kit'];
            }
        }

        $container = new ContainerBuilder();
        $extension = new C33sConstructionKitExtension();
        $extension->load($configs, $container);

        $mappingData = $container->getParameter('c33s_construction_kit.raw_mapping_data');
        $composerBlocks = $container->getParameter('c33s_construction_kit.building_blocks.composer');

        $configManipulator = new ConfigManipulator(array('', 'prod', 'dev', 'test'), $kernel->getRootDir(), $logger);
        $mapping = new BuildingBlockMapping($mappingData, $composerBlocks, $configManipulator, $logger);
        $writer = new MappingWriter($mapping, $configManipulator, $kernel, $logger);

        return $writer;
    }

    protected function getDefaultConfigManipulator($kernelRootDir)
    {
        $manipulator = new ConfigManipulator(array('', 'prod', 'dev', 'test'), $kernelRootDir, new NullLogger());
    }
}
