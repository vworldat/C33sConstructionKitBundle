<?php

namespace C33s\ConstructionKitBundle\BuildingBlock;

use Symfony\Component\HttpKernel\KernelInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

class BuildingBlockHandler
{
    /**
     *
     * @var string
     */
    protected $kernel;

    /**
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @param string $rootDir   Kernel root dir
     */
    public function __construct(KernelInterface $kernel, LoggerInterface $logger)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
    }

    public function updateBlocks()
    {
        $this->getAllAvailableBlocks();
    }

    protected function getAllAvailableBlocks()
    {

    }
}
