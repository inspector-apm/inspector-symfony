<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\DependencyInjection\Compiler;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\HttpClient\TraceableHttpClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use ReturnTypeWillChange;

use function interface_exists;

class HttpClientCompilerPass implements CompilerPassInterface
{
    #[ReturnTypeWillChange]
    public function process(ContainerBuilder $container): void
    {
        $config = $container->getParameter('inspector.configuration.definition');

        if (true !== $config['enabled'] || true !== $config['http_client'] || empty($config['ingestion_key'])) {
            return;
        }

        if (!interface_exists(\Symfony\Contracts\HttpClient\HttpClientInterface::class)) {
            return;
        }

        $clientIds = [];

        // Default http_client service
        if ($container->hasDefinition('http_client')) {
            $clientIds[] = 'http_client';
        }

        // Scoped clients tagged with http_client.client
        foreach ($container->findTaggedServiceIds('http_client.client') as $id => $tags) {
            $clientIds[] = $id;
        }

        foreach ($clientIds as $clientId) {
            $decoratorId = 'inspector.http_client.'.$clientId;
            $innerId = $decoratorId.'.inner';

            $definition = new Definition(TraceableHttpClient::class, [
                new Reference($innerId),
                new Reference(Inspector::class),
            ]);
            $definition->setDecoratedService($clientId, $innerId);
            $definition->setPublic(false);

            $container->setDefinition($decoratorId, $definition);
        }
    }
}
