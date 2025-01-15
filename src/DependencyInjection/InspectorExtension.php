<?php


namespace Inspector\Symfony\Bundle\DependencyInjection;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Twig\TwigTracer;
use Inspector\Symfony\Bundle\Listeners\ConsoleEventsSubscriber;
use Inspector\Symfony\Bundle\Listeners\MessengerEventsSubscriber;
use Inspector\Symfony\Bundle\Listeners\KernelEventsSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;

class InspectorExtension extends Extension
{
    /**
     * Current version of the bundle.
     */
    const VERSION = '1.4.0';

    /**
     * Loads a specific configuration.
     *
     * @throws \InvalidArgumentException|\Exception When provided tag is not defined in this extension
     */
    #[\ReturnTypeWillChange]
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('inspector.configuration.definition', $config);

        /*
         * Inspector configuration
         */
        $inspectorConfigDefinition = new Definition(\Inspector\Configuration::class, [$config['ingestion_key']]);
        $inspectorConfigDefinition->setPublic(false);
        $inspectorConfigDefinition->addMethodCall('setEnabled', [$config['enabled']]);
        $inspectorConfigDefinition->addMethodCall('setUrl', [$config['url']]);
        $inspectorConfigDefinition->addMethodCall('setTransport', [$config['transport']]);
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
        // Determine if it's on Symfony Security Bundle >= 7 or <= 6.
        if (class_exists(Security::class)) {
            // Symfony Security Bundle <= 6
            // Symfony\Component\Security\Core\Security exists since 7.
            $kernelEventsSubscriberDefinition = new Definition(KernelEventsSubscriber::class, [
                new Reference(Inspector::class),
                new Reference(RouterInterface::class),
                new Reference(Security::class),
                null,
                $config['ignore_routes']
            ]);
        } elseif (class_exists(TokenStorageInterface::class)) {
            // Symfony Security Bundle >= 7
            $kernelEventsSubscriberDefinition = new Definition(KernelEventsSubscriber::class, [
                new Reference(Inspector::class),
                new Reference(RouterInterface::class),
                null,
                new Reference(TokenStorageInterface::class),
                $config['ignore_routes']
            ]);
        } else {
            // No Symfony Security Bundle
            $kernelEventsSubscriberDefinition = new Definition(KernelEventsSubscriber::class, [
                new Reference(Inspector::class),
                new Reference(RouterInterface::class),
                null,
                null,
                $config['ignore_routes']
            ]);
        }
        $kernelEventsSubscriberDefinition->setPublic(false)->addTag('kernel.event_subscriber');
        $container->setDefinition(KernelEventsSubscriber::class, $kernelEventsSubscriberDefinition);

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
         * Messenger event subscriber
         */
        if (interface_exists(\Symfony\Component\Messenger\MessageBusInterface::class) && true === $config['messenger']) {
            $messengerEventsSubscriber = new Definition(MessengerEventsSubscriber::class, [
                new Reference(Inspector::class),
                $config['ignore_messages']??[]
            ]);

            $messengerEventsSubscriber->setPublic(false)->addTag('kernel.event_subscriber');
            $container->setDefinition(MessengerEventsSubscriber::class, $messengerEventsSubscriber);
        }

        /*
         * Twig
         */
        if (true === $config['templates']) {
            $inspectableTwigExtensionDefinition = new Definition(TwigTracer::class, [
                new Reference(Inspector::class),
            ]);

            $inspectableTwigExtensionDefinition->addTag('twig.extension');
            $container->setDefinition(TwigTracer::class, $inspectableTwigExtensionDefinition);
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
