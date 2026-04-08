<?php

namespace Inspector\Symfony\Bundle\Tests\HttpClient;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Symfony\Bundle\HttpClient\TraceableHttpClient;
use Inspector\Symfony\Bundle\HttpClient\TraceableResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class TraceableHttpClientTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(HttpClientInterface::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
    }

    private HttpClientInterface&MockObject $innerClient;
    private Inspector&MockObject $inspector;
    private TraceableHttpClient $traceableClient;

    protected function setUp(): void
    {
        $this->innerClient = $this->createMock(HttpClientInterface::class);
        $this->inspector = $this->createMock(Inspector::class);
        $this->traceableClient = new TraceableHttpClient($this->innerClient, $this->inspector);
    }

    public function testRequestReturnsTraceableResponse(): void
    {
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($this->createSegment());

        $innerResponse = $this->createMock(ResponseInterface::class);
        $this->innerClient->method('request')->willReturn($innerResponse);

        $response = $this->traceableClient->request('GET', 'https://example.com/api');

        $this->assertInstanceOf(TraceableResponse::class, $response);
    }

    public function testRequestStartsSegmentWithContext(): void
    {
        $segment = $this->createSegment();

        $this->inspector->expects($this->once())->method('canAddSegments')->willReturn(true);
        $this->inspector->expects($this->once())->method('startSegment')
            ->with('http.client', 'GET https://example.com/api')
            ->willReturn($segment);
        $segment->expects($this->once())->method('addContext')
            ->with('http', $this->callback(function (array $context) {
                return $context['method'] === 'GET'
                    && $context['url'] === 'https://example.com/api'
                    && $context['host'] === 'example.com';
            }));

        $this->innerClient->method('request')->willReturn($this->createMock(ResponseInterface::class));

        $this->traceableClient->request('GET', 'https://example.com/api');
    }

    public function testRequestStartsSegmentWithContextWithPort(): void
    {
        $segment = $this->createSegment();

        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($segment);
        $segment->expects($this->once())->method('addContext')
            ->with('http', $this->callback(function (array $context) {
                return $context['host'] === 'example.com:8080';
            }));

        $this->innerClient->method('request')->willReturn($this->createMock(ResponseInterface::class));

        $this->traceableClient->request('POST', 'https://example.com:8080/api');
    }

    public function testRequestSkipsSegmentWhenCannotAddSegments(): void
    {
        $this->inspector->expects($this->once())->method('canAddSegments')->willReturn(false);
        $this->inspector->expects($this->never())->method('startSegment');

        $innerResponse = $this->createMock(ResponseInterface::class);
        $this->innerClient->method('request')->willReturn($innerResponse);

        $response = $this->traceableClient->request('GET', 'https://example.com/api');

        $this->assertNotInstanceOf(TraceableResponse::class, $response);
    }

    public function testStreamUnwrapsTraceableResponses(): void
    {
        $segment = $this->createSegment();
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($segment);

        $innerResponse = $this->createMock(ResponseInterface::class);
        $this->innerClient->method('request')->willReturn($innerResponse);

        $traceableResponse = $this->traceableClient->request('GET', 'https://example.com/api');

        $stream = $this->createMock(ResponseStreamInterface::class);
        $this->innerClient->expects($this->once())->method('stream')
            ->with($this->callback(function (array $responses) use ($innerResponse) {
                return count($responses) === 1 && $responses[0] === $innerResponse;
            }))
            ->willReturn($stream);

        $result = $this->traceableClient->stream($traceableResponse);
        $this->assertSame($stream, $result);
    }

    public function testStreamPassesRegularResponsesThrough(): void
    {
        $regularResponse = $this->createMock(ResponseInterface::class);

        $stream = $this->createMock(ResponseStreamInterface::class);
        $this->innerClient->expects($this->once())->method('stream')
            ->with([$regularResponse])
            ->willReturn($stream);

        $result = $this->traceableClient->stream([$regularResponse]);
        $this->assertSame($stream, $result);
    }

    public function testWithOptionsReturnsNewInstance(): void
    {
        $newInnerClient = $this->createMock(HttpClientInterface::class);
        $this->innerClient->method('withOptions')->willReturn($newInnerClient);

        $newClient = $this->traceableClient->withOptions(['timeout' => 30]);

        $this->assertInstanceOf(TraceableHttpClient::class, $newClient);
        $this->assertNotSame($this->traceableClient, $newClient);
    }

    private function createSegment(): Segment&MockObject
    {
        $segment = $this->createMock(Segment::class);
        $segment->method('addContext')->willReturnSelf();
        $segment->method('setColor')->willReturnSelf();
        return $segment;
    }
}
