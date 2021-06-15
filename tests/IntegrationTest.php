<?php

namespace Inspector\Symfony\Bundle\Tests;

use Inspector\Inspector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IntegrationTest extends KernelTestCase
{
    public function testServiceWiring()
    {
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        $inspectorService = static::getContainer()->get('inspector');

        $this->assertInstanceOf(Inspector::class, $inspectorService);
    }

    public function testServiceWiringWithConfiguration()
    {
        self::bootKernel(['environment' => 'test', 'debug' => false]);

        $inspectorService = static::getContainer()->get('inspector');

        $this->assertFalse($inspectorService->hasTransaction());
    }
}

