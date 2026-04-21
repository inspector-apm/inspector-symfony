<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\Integration;

use Inspector\Symfony\Bundle\Listeners\KernelEventsSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HttpMonitoringTest extends WebTestCase
{
    public function testSubscriberIsRegistered(): void
    {
        self::bootKernel();

        $container = method_exists(static::class, 'getContainer')
            ? static::getContainer()
            : self::$container;

        $this->assertTrue($container->has(KernelEventsSubscriber::class));
    }

    public function testSuccessfulRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('OK', $client->getResponse()->getContent());
    }

    public function testExceptionRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/error');

        $this->assertSame(500, $client->getResponse()->getStatusCode());
    }
}
