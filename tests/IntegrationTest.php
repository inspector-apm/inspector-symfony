<?php


namespace Inspector\Symfony\Bundle\Tests;


use Inspector\Inspector;
use Inspector\Symfony\Bundle\InspectorBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class IntegrationTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new InspectorTestingKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        $inspectorService = $container->get('inspector');

        $this->assertInstanceOf(Inspector::class, $inspectorService);
    }

    public function testServiceWiringWithConfiguration()
    {
        $kernel = new InspectorTestingKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        $inspectorService = $container->get('inspector');

        $this->assertFalse($inspectorService->hasTransaction());
    }
}

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
    public function __construct(array $inspectorConfig = [])
    {
        $this->inspectorConfig = $inspectorConfig;

        parent::__construct('test', true);
    }

    /**
     * @inheritDoc
     */
    public function registerBundles()
    {
        return [
            new InspectorBundle()
        ];
    }

    /**
     * @inheritDoc
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('inspector', $this->inspectorConfig);
        });
    }

    /**
     * @inheritDoc
     */
    public function getCacheDir()
    {
        $currentCache = __DIR__.'/cache_'.spl_object_hash($this);

        foreach(glob(__DIR__ . "/cache_*") as $item) {
            if (is_dir($item) && $currentCache != $item)
                $this->deleteDirectory($item);
        }

        return $currentCache;
    }

    /**
     * Recursive delete a directory.
     *
     * @param string $dir
     * @return bool
     */
    protected function deleteDirectory($dir) {
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

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }

        }

        return rmdir($dir);
    }
}
