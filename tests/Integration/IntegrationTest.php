<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\Integration;

use Inspector\Inspector;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function method_exists;

class IntegrationTest extends KernelTestCase
{
    private static function getInspectorService(): Inspector
    {
        self::bootKernel(['environment' => 'test']);

        // static::getContainer() was added in Symfony 5.3; fall back to
        // the deprecated self::$container for older versions.
        if (method_exists(static::class, 'getContainer')) {
            return static::getContainer()->get(Inspector::class);
        }

        return self::$container->get(Inspector::class);
    }

    public function testServiceWiring()
    {
        $inspectorService = self::getInspectorService();

        $this->assertInstanceOf(Inspector::class, $inspectorService);
    }

    public function testServiceWiringWithConfiguration()
    {
        $inspectorService = self::getInspectorService();

        $this->assertFalse($inspectorService->hasTransaction());
    }
}
