<?php

namespace Inspector\Symfony\Bundle\Tests\Integration;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Inspector\Symfony\Bundle\InspectorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Class InspectorTestingKernel
 * @package Inspector\Symfony\Tests
 */
class InspectorTestingKernel extends Kernel
{
    use MicroKernelTrait;

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
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new InspectorBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/'.$this->environment.'/*.yaml');

        if (is_file(\dirname(__DIR__).'/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_'.$this->environment.'.yaml');
        } elseif (is_file($path = \dirname(__DIR__).'/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }
    }

//
//    /**
//     * @inheritDoc
//     */
//    public function registerContainerConfiguration(LoaderInterface $loader)
//    {
//        $loader->load(
//            function (ContainerBuilder $container) {
//                $container->loadFromExtension('inspector', $this->inspectorConfig);
//            }
//        );
//    }
}
