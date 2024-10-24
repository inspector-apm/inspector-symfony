<?php

namespace Inspector\Symfony\Bundle\Tests\Integration;

use Inspector\Inspector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IntegrationTest extends KernelTestCase
{
    public function testServiceWiring()
    {
        self::bootKernel(['environment' => 'test']);

        $inspectorService = static::getContainer()->get(Inspector::class);

        $this->assertInstanceOf(Inspector::class, $inspectorService);
    }

    public function testServiceWiringWithConfiguration()
    {
        self::bootKernel(['environment' => 'test']);

        $inspectorService = static::getContainer()->get(Inspector::class);

        $this->assertFalse($inspectorService->hasTransaction());
    }
}

