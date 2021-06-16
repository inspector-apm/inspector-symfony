<?php


namespace Inspector\Symfony\Bundle\DependencyInjection;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Listeners\ConsoleEventsSubscriber;
use Inspector\Symfony\Bundle\Listeners\KernelEventsSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class InspectorExtension extends Extension
{
    protected const IGNORED_ROUTES = ['_wdt', '_profiler', '_profiler_home', '_profiler_search', '_profiler_search_bar',
        '_profiler_phpinfo', '_profiler_search_results', '_profiler_open_file', '_profiler_router',
        '_profiler_exception', '_profiler_exception_css',
    ];

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('inspector.configuration', $config);

        if(true !== $config['enabled']) {
            return;
        }

        // Inspector configuration
        $inspectorConfigDefinition = new Definition(\Inspector\Configuration::class, [$config['ingestion_key']]);
        $inspectorConfigDefinition->setPublic(false);
        $inspectorConfigDefinition->addMethodCall('setEnabled', [$config['enabled']]);
        $inspectorConfigDefinition->addMethodCall('setUrl', [$config['url']]);
        $inspectorConfigDefinition->addMethodCall('setTransport', [$config['transport']]);
        $inspectorConfigDefinition->addMethodCall('serverSamplingRatio', [$config['server_sampling_ratio']]);

        $container->setDefinition('inspector.configuration.internal', $inspectorConfigDefinition);

        // Inspector service itself
        $inspectorDefinition = new Definition(Inspector::class, [$inspectorConfigDefinition]);
        $inspectorDefinition->setPublic(true);

        $container->setDefinition('inspector', $inspectorDefinition);

        $config['ignore_routes'] = array_merge(
            $config['ignore_routes'],
            self::IGNORED_ROUTES
        );

        // Kernel events subscriber: request, response etc.
        $kernelEventsSubscriberDefinition = new Definition(KernelEventsSubscriber::class, [
            new Reference('inspector'),
            new Reference('router'),
            new Reference('security.helper'),
            $config['ignore_routes']
        ]);
        $kernelEventsSubscriberDefinition->setPublic(false)->addTag('kernel.event_subscriber');

        $container->setDefinition(KernelEventsSubscriber::class, $kernelEventsSubscriberDefinition);

        // Console events subscriber
        $consoleEventsSubscriberDefinition = new Definition(ConsoleEventsSubscriber::class, [
            new Reference('inspector'),
            $config['ignore_commands'],
        ]);
        $consoleEventsSubscriberDefinition->setPublic(false)->addTag('kernel.event_subscriber');

        $container->setDefinition(ConsoleEventsSubscriber::class, $consoleEventsSubscriberDefinition);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
