<?php


namespace Inspector\Symfony\Bundle\DependencyInjection;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Inspectable\Twig\InspectableTwigExtension;
use Inspector\Symfony\Bundle\Listeners\ConsoleEventsSubscriber;
use Inspector\Symfony\Bundle\Listeners\KernelEventsSubscriber;
use Inspector\Symfony\Bundle\Listeners\MessengerEventsSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

class InspectorExtension extends Extension
{
    /**
     * Current version of the bundle.
     */
    const VERSION = '1.0.6';

    /**
     * Loads a specific configuration.
     *
     * @throws \InvalidArgumentException|\Exception When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('inspector.configuration.definition', $config);

        if(empty($config['ingestion_key'])) {
            return;
        }

        /*
         * Inspector configuration
         */
        $inspectorConfigDefinition = new Definition(\Inspector\Configuration::class, [$config['ingestion_key']]);
        $inspectorConfigDefinition->setPublic(false);
        $inspectorConfigDefinition->addMethodCall('setEnabled', [$config['enabled']]);
        $inspectorConfigDefinition->addMethodCall('setUrl', [$config['url']]);
        $inspectorConfigDefinition->addMethodCall('setTransport', [$config['transport']]);
        $inspectorConfigDefinition->addMethodCall('serverSamplingRatio', [$config['server_sampling_ratio']]);
        $inspectorConfigDefinition->addMethodCall('setVersion', [self::VERSION]);

        $container->setDefinition(\Inspector\Configuration::class, $inspectorConfigDefinition);

        /*
         * Inspector service itself
         */
        $inspectorDefinition = new Definition(Inspector::class, [$inspectorConfigDefinition]);
        $inspectorDefinition->setPublic(true);
        $container->setDefinition(Inspector::class, $inspectorDefinition);

        /*
         * Kernel events subscriber: request, response etc.
         */
        $kernelEventsSubscriberDefinition = new Definition(KernelEventsSubscriber::class, [
            new Reference(Inspector::class),
            new Reference(RouterInterface::class),
            new Reference(Security::class),
            $config['ignore_routes']
        ]);
        $kernelEventsSubscriberDefinition->setPublic(false)->addTag('kernel.event_subscriber');
        $container->setDefinition(KernelEventsSubscriber::class, $kernelEventsSubscriberDefinition);

        /*
         * Connect the messenger event subscriber
         */
        if (interface_exists(MessageBusInterface::class) && true === $config['messenger']) {
            $messengerEventsSubscriber = new Definition(MessengerEventsSubscriber::class, [
                new Reference(Inspector::class)
            ]);

            $messengerEventsSubscriber->setPublic(false)->addTag('kernel.event_subscriber');
            $container->setDefinition(MessengerEventsSubscriber::class, $messengerEventsSubscriber);
        }

        /*
         * Console events subscriber
         */
        $consoleEventsSubscriberDefinition = new Definition(ConsoleEventsSubscriber::class, [
            new Reference(Inspector::class),
            $config['ignore_commands'],
        ]);

        $consoleEventsSubscriberDefinition->setPublic(false)->addTag('kernel.event_subscriber');
        $container->setDefinition(ConsoleEventsSubscriber::class, $consoleEventsSubscriberDefinition);

        /*
         * Twig
         */
        if (true === $config['templates']) {
            $inspectableTwigExtensionDefinition = new Definition(InspectableTwigExtension::class, [
                new Reference(Inspector::class),
            ]);

            $inspectableTwigExtensionDefinition->addTag('twig.extension');
            $container->setDefinition(InspectableTwigExtension::class, $inspectableTwigExtensionDefinition);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
