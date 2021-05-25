<?php

namespace Inspector\Symfony\Bundle\DependencyInjection\Compiler;

/** Compatibility with doctrine/dbal < 2.10.0 */
use Doctrine\DBAL\Logging\LoggerChain;
use Doctrine\DBAL\SQLParserUtils;
/** End compatibility with doctrine/dbal < 2.10.0 */
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineDBALCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     */
    public function process(ContainerBuilder $container)
    {
        $logger = new Reference('doctrine.dbal.logger.inspectable');
        $chainLogger = $container->getDefinition('doctrine.dbal.logger.chain');
        if (! method_exists(SQLParserUtils::class, 'getPositionalPlaceholderPositions') && method_exists(LoggerChain::class, 'addLogger')) {
            // doctrine/dbal < 2.10.0
            $chainLogger->addMethodCall('addLogger', [$logger]);
        } else {
            $loggers = $chainLogger->getArgument(0);
            array_push($loggers, $logger);
            $chainLogger->replaceArgument(0, $loggers);
        }
    }
}
