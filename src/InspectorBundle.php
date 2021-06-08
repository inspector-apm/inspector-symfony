<?php

namespace Inspector\Symfony\Bundle;

use Inspector\Symfony\Bundle\DependencyInjection\Compiler\DoctrineDBALCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class InspectorBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // If inspector is disabled in configuration, there shouldn't be a definition
        if (!$container->hasDefinition('inspector')) {
            return;
        }

        $container->addCompilerPass(new DoctrineDBALCompilerPass());
    }
}
