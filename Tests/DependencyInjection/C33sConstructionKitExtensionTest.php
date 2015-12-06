<?php

namespace C33s\ConstructionKitBundle\Tests\DependencyInjection;

use C33s\ConstructionKitBundle\DependencyInjection\C33sConstructionKitExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class C33sConstructionKitExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testExtensionLoadsSomething()
    {
        $container = new ContainerBuilder();
        $extension = new C33sConstructionKitExtension();
        $extension->load(array(), $container);
        $this->assertNotCount(0, $container->getDefinitions(), 'Extension contains at least one service definition');
    }
}
