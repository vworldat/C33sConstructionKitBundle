<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

class ConstructionKitBuildingBlock extends SimpleBuildingBlock
{
    /**
     * Return true if this block should be installed automatically as soon as it is registered (e.g. using composer).
     * This is the only public method that should not rely on a previously injected Kernel.
     *
     * @return bool
     */
    public function isAutoInstall()
    {
        return true;
    }

    /**
     * Get the fully namespaced classes of all bundles that should be enabled to use this BuildingBlock.
     * These will be used in AppKernel.php.
     *
     * @return array
     */
    public function getBundleClasses()
    {
        return array(
            'C33s\\ConstructionKitBundle\\C33sConstructionKitBundle',
        );
    }
}
