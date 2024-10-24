<?php

namespace Inspector\Symfony\Bundle\DependencyInjection\Compiler;

/** Compatibility with doctrine/dbal < 2.10.0 */

use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\SQLParserUtils;
use Inspector\Inspector;
use Inspector\Symfony\Bundle\Doctrine\V2\InspectorSQLLogger;
use Inspector\Symfony\Bundle\Doctrine\Middleware\InspectorMiddleware;
use OutOfBoundsException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/** End compatibility with doctrine/dbal < 2.10.0 */
class DoctrineDBALCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $config = $container->getParameter('inspector.configuration.definition');

        if (true !== $config['enabled'] || true !== $config['query'] || empty($config['ingestion_key'])) {
            // Inspector is not enabled, no service required.
            return;
        }

        if (!$container->hasDefinition('doctrine')) {
            // No Doctrine service, no service required.
            return;
        }

        /** @var array<string, string> $connections */
        $connections = $container->getParameter('doctrine.connections');
        if (count($connections) === 0) {
            // No Doctrine connections configured, no service required.
            return;
        }

        if (class_exists(Result::class)) {
            // Doctrine DBAL 3+
            foreach ($connections as $name => $service) {
                $inspectorMiddlewareDefinition = new Definition(InspectorMiddleware::class, [
                    new Reference(Inspector::class),
                    $config,
                    $name
                ]);
                $middlewareDefinitionName = sprintf('doctrine.dbal.inspector_middleware.%s', $name);
                $inspectorMiddlewareDefinition->addTag('doctrine.middleware', ['connections' => [$name]]);
                $container->setDefinition($middlewareDefinitionName, $inspectorMiddlewareDefinition);
            }

            return;
        }

        // Doctrine DBAL 2
        if (!$container->hasDefinition('doctrine.dbal.logger.chain')) {
            // Without DBAL Logger Chain it's not possible to add Inspector SQL Logger
            return;
        }

        $chainLogger = $container->getDefinition('doctrine.dbal.logger.chain');
        foreach ($connections as $name => $service) {
            // SQL Logger for Doctrine DBAL to use in Inspector.dev
            $inspectorSqlLoggerDefinition = new Definition(InspectorSQLLogger::class, [
                new Reference(Inspector::class),
                $config,
                $name,
            ]);

            $loggerDefinitionName = sprintf('doctrine.dbal.%s_connection.logger.inspectable', $name);
            $container->setDefinition($loggerDefinitionName, $inspectorSqlLoggerDefinition);

            // Adding inspectable logger to the Doctrine logger
            $logger = new Reference($loggerDefinitionName);
            if (!method_exists(SQLParserUtils::class, 'getPositionalPlaceholderPositions') && method_exists(LoggerChain::class, 'addLogger')) {
                // doctrine/dbal < 2.10.0
                $chainLogger->addMethodCall('addLogger', [$logger]);
            } else {
                try {
                    $loggers = $chainLogger->getArgument(0);
                    $loggers[] = $logger;
                    $chainLogger->replaceArgument(0, $loggers);
                } catch (OutOfBoundsException $exception) {
                    $chainLogger->addArgument([$logger]);
                }
            }

            $container->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))->addMethodCall('setSQLLogger', [$logger]);
        }
    }
}
