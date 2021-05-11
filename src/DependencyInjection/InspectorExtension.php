<?php


namespace Inspector\Symfony\Bundle\DependencyInjection;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class InspectorExtension extends Extension
{
    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // Inspector configuration
        $inspectorConfigDefinition = new Definition(\Inspector\Configuration::class, [$config['ingestion_key']]);
        $inspectorConfigDefinition->setPublic(false);
        $inspectorConfigDefinition->addMethodCall('setEnabled', [$config['enabled']]);
        $inspectorConfigDefinition->addMethodCall('setUrl', [$config['url']]);
        $inspectorConfigDefinition->addMethodCall('setTransport', [$config['transport']]);

        $container->setDefinition('inspector.configuration', $inspectorConfigDefinition);

        $container->getDefinition('inspector')
            ->setArgument(0, $inspectorConfigDefinition);
    }
}
