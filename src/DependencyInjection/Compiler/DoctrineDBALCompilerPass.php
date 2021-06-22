<?php

namespace Inspector\Symfony\Bundle\DependencyInjection\Compiler;

/** Compatibility with doctrine/dbal < 2.10.0 */
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\SQLParserUtils;
/** End compatibility with doctrine/dbal < 2.10.0 */

use Inspector\Symfony\Bundle\Inspectable\Doctrine\DBAL\Logging\InspectableSQLLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineDBALCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $config = $container->getParameter('inspector.configuration');

        if (true !== $config['enabled']) {
            return;
        }

        if (true !== $config['query']) {
            return;
        }

        if (!$container->hasDefinition('doctrine') || !$container->hasDefinition('doctrine.dbal.logger.chain')) {
            return;
        }

        $chainLogger = $container->getDefinition('doctrine.dbal.logger.chain');

        /** @var array<string, string> $connections */
        $connections = $container->getParameter('doctrine.connections');
        foreach ($connections as $name => $service) {
            // SQL Logger for Doctrine DBAL to use in Inspector.dev
            $inspectableSqlLoggerDefinition = new Definition(InspectableSQLLogger::class, [
                new Reference('inspector'),
                $config,
                $name
            ]);

            $loggerDefinitionName = sprintf('doctrine.dbal.%s_connection.logger.inspectable', $name);
            $container->setDefinition($loggerDefinitionName, $inspectableSqlLoggerDefinition);

            // Adding inspectable logger to the Doctrine logger
            $logger = new Reference($loggerDefinitionName);
            if (! method_exists(SQLParserUtils::class, 'getPositionalPlaceholderPositions') && method_exists(LoggerChain::class, 'addLogger')) {
                // doctrine/dbal < 2.10.0
                $chainLogger->addMethodCall('addLogger', [$logger]);
            } else {
                $loggers = $chainLogger->getArgument(0);
                array_push($loggers, $logger);
                $chainLogger->replaceArgument(0, $loggers);
            }

            $container->getDefinition(sprintf('doctrine.dbal.%s_connection.configuration', $name))->addMethodCall('setSQLLogger', [$logger]);
        }
    }
}
