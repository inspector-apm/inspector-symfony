<?php

namespace Inspector\Symfony\Bundle\Tests;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Inspector\Symfony\Bundle\InspectorBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class InspectorTestingKernel
 * @package Inspector\Symfony\Tests
 */
class InspectorTestingKernel extends Kernel
{
    private $inspectorConfig;

    /**
     * @inheritDoc
     */
    public function __construct(string $environment, bool $debug, array $inspectorConfig = [])
    {
        $this->inspectorConfig = $inspectorConfig;

        parent::__construct($environment, $debug);
    }

    /**
     * @inheritDoc
     */
    public function registerBundles()
    {
        return [
            new DoctrineBundle(),
            new InspectorBundle(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(
            function (ContainerBuilder $container) {
                $container->loadFromExtension('inspector', $this->inspectorConfig);
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function getCacheDir()
    {
        $currentCache = __DIR__.'/cache_'.spl_object_hash($this);

        foreach (glob(__DIR__."/cache_*") as $item) {
            if (is_dir($item) && $currentCache != $item) {
                $this->deleteDirectory($item);
            }
        }

        return $currentCache;
    }

    /**
     * Recursive delete a directory.
     *
     * @param string $dir
     * @return bool
     */
    protected function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }

        }

        return rmdir($dir);
    }
}
