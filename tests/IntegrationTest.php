<?php

namespace Inspector\Symfony\Bundle\Tests;

use Inspector\Inspector;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testServiceWiring()
    {
        $kernel = new InspectorTestingKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        $inspectorService = $container->get('inspector');

        $this->assertInstanceOf(Inspector::class, $inspectorService);
    }

    public function testServiceWiringWithConfiguration()
    {
        $kernel = new InspectorTestingKernel();
        $kernel->boot();

        $container = $kernel->getContainer();

        $inspectorService = $container->get('inspector');

        $this->assertFalse($inspectorService->hasTransaction());
    }
}

