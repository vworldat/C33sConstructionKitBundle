<?php

namespace C33s\ConstructionKitBundle;

use C33s\ConstructionKitBundle\DependencyInjection\Compiler\BuildingBlocksPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class C33sConstructionKitBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new BuildingBlocksPass());
    }
}
