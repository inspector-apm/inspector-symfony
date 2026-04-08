<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\DependencyInjection\Compiler;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\DependencyInjection\Compiler\HttpClientCompilerPass;
use Inspector\Symfony\Bundle\HttpClient\TraceableHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use stdClass;

use function array_merge;
use function interface_exists;

class HttpClientCompilerPassTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(HttpClientInterface::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
    }

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();

        // Register Inspector services (required by the decorator)
        $this->container->setDefinition(Inspector::class, new Definition(Inspector::class));
    }

    private function setConfig(array $config): void
    {
        $this->container->setParameter('inspector.configuration.definition', array_merge([
            'enabled' => true,
            'http_client' => true,
            'ingestion_key' => 'test-key',
        ], $config));
    }

    public function testDecoratesDefaultHttpClient(): void
    {
        $this->setConfig([]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $decoratorId = 'inspector.http_client.http_client';
        $this->assertTrue($this->container->hasDefinition($decoratorId));

        $decorator = $this->container->getDefinition($decoratorId);
        $this->assertSame(TraceableHttpClient::class, $decorator->getClass());
    }

    public function testDecoratesScopedClientsTaggedWithHttpClientClient(): void
    {
        $this->setConfig([]);

        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        $scopedClient = new Definition(stdClass::class);
        $scopedClient->addTag('http_client.client');
        $this->container->setDefinition('acme.api_client', $scopedClient);

        (new HttpClientCompilerPass())->process($this->container);

        $this->assertTrue($this->container->hasDefinition('inspector.http_client.http_client'));
        $this->assertTrue($this->container->hasDefinition('inspector.http_client.acme.api_client'));
    }

    public function testSkipsWhenDisabled(): void
    {
        $this->setConfig(['enabled' => false]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $this->assertFalse($this->container->hasDefinition('inspector.http_client.http_client'));
    }

    public function testSkipsWhenHttpClientConfigIsFalse(): void
    {
        $this->setConfig(['http_client' => false]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $this->assertFalse($this->container->hasDefinition('inspector.http_client.http_client'));
    }

    public function testSkipsWhenIngestionKeyIsEmpty(): void
    {
        $this->setConfig(['ingestion_key' => null]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $this->assertFalse($this->container->hasDefinition('inspector.http_client.http_client'));
    }

    public function testSkipsWhenNoHttpClientService(): void
    {
        $this->setConfig([]);

        (new HttpClientCompilerPass())->process($this->container);

        $this->assertFalse($this->container->hasDefinition('inspector.http_client.http_client'));
    }

    public function testDecoratorReferencesInspector(): void
    {
        $this->setConfig([]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $decorator = $this->container->getDefinition('inspector.http_client.http_client');
        $references = $decorator->getArguments();

        // Second argument should be a Reference to Inspector
        $this->assertInstanceOf(\Symfony\Component\DependencyInjection\Reference::class, $references[1]);
        $this->assertSame(Inspector::class, (string) $references[1]);
    }

    public function testDecoratorIsNotPublic(): void
    {
        $this->setConfig([]);
        $this->container->setDefinition('http_client', new Definition(stdClass::class));

        (new HttpClientCompilerPass())->process($this->container);

        $decorator = $this->container->getDefinition('inspector.http_client.http_client');
        $this->assertFalse($decorator->isPublic());
    }
}
