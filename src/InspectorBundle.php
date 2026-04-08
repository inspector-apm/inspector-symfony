<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle;

use Inspector\Symfony\Bundle\DependencyInjection\Compiler\DoctrineDBALCompilerPass;
use Inspector\Symfony\Bundle\DependencyInjection\Compiler\HttpClientCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class InspectorBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Doctrine DBAL middlewares are injected in the DoctrineBundle MiddlewaresPass
        // (doctrine-bundle/src/DependencyInjection/Compiler/MiddlewaresPass.php)
        // so this compiler pass must have a higher priority.
        $container->addCompilerPass(new DoctrineDBALCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);

        $container->addCompilerPass(new HttpClientCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
    }
}
