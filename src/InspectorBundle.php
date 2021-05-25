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

        $container->addCompilerPass(new DoctrineDBALCompilerPass());
    }
}
