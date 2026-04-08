<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\HttpClient;

use Inspector\Models\Segment;
use Inspector\Symfony\Bundle\HttpClient\TraceableResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function interface_exists;

class TraceableResponseTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(HttpClientInterface::class)) {
            self::markTestSkipped('symfony/http-client is not installed.');
        }
    }

    private ResponseInterface&MockObject $innerResponse;
    private Segment&MockObject $segment;
    private TraceableResponse $traceableResponse;

    protected function setUp(): void
    {
        $this->innerResponse = $this->createMock(ResponseInterface::class);
        $this->segment = $this->createMock(Segment::class);
        $this->segment->method('addContext')->willReturnSelf();
        $this->segment->method('setColor')->willReturnSelf();
        $this->traceableResponse = new TraceableResponse(
            $this->innerResponse,
            $this->segment,
            'GET',
            'https://example.com/api'
        );
    }

    protected function tearDown(): void
    {
        // Prevent __destruct from calling end() on a mock with no expectations
        // by consuming the response (which ends the segment)
        try {
            $this->traceableResponse->getInfo();
        } catch (Throwable $e) {
            // ignore if already destroyed
        }
    }

    public function testGetStatusCodeEndsSegment(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(200);

        $this->segment->expects($this->once())->method('addContext')
            ->with('http', ['status_code' => 200]);
        $this->segment->expects($this->once())->method('end');

        $this->assertSame(200, $this->traceableResponse->getStatusCode());
    }

    public function testGetStatusCodeSetColorForClientError(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(404);

        $this->segment->expects($this->once())->method('setColor')->with('red');
        $this->segment->expects($this->once())->method('end');

        $this->assertSame(404, $this->traceableResponse->getStatusCode());
    }

    public function testGetStatusCodeSetColorForServerError(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(500);

        $this->segment->expects($this->once())->method('setColor')->with('red');
        $this->segment->expects($this->once())->method('end');

        $this->assertSame(500, $this->traceableResponse->getStatusCode());
    }

    public function testGetStatusCodeDoesNotSetColorForSuccess(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(200);

        $this->segment->expects($this->never())->method('setColor');
        $this->segment->expects($this->once())->method('end');

        $this->traceableResponse->getStatusCode();
    }

    public function testGetContentRecordsSizeAndEndsSegment(): void
    {
        $this->innerResponse->method('getContent')->willReturn('{"ok":true}');

        $this->segment->expects($this->once())->method('addContext')
            ->with('http', ['response_body_size' => 11]);
        $this->segment->expects($this->once())->method('end');

        $this->assertSame('{"ok":true}', $this->traceableResponse->getContent());
    }

    public function testGetHeadersRecordsHeadersAndEndsSegment(): void
    {
        $headers = ['content-type' => ['application/json']];
        $this->innerResponse->method('getHeaders')->willReturn($headers);

        $this->segment->expects($this->once())->method('addContext')
            ->with('http', ['response_headers' => $headers]);
        $this->segment->expects($this->once())->method('end');

        $this->assertSame($headers, $this->traceableResponse->getHeaders());
    }

    public function testToArrayEndsSegment(): void
    {
        $data = ['status' => 'ok'];
        $this->innerResponse->method('toArray')->willReturn($data);

        $this->segment->expects($this->once())->method('end');

        $this->assertSame($data, $this->traceableResponse->toArray());
    }

    public function testCancelEndsSegmentAndCancelsInner(): void
    {
        $this->innerResponse->expects($this->once())->method('cancel');
        $this->segment->expects($this->once())->method('end');

        $this->traceableResponse->cancel();
    }

    public function testSegmentIsEndedOnlyOnce(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(200);

        $this->segment->expects($this->once())->method('end');

        $this->traceableResponse->getStatusCode();
        // Calling again should not trigger another end()
        $this->traceableResponse->getStatusCode();
    }

    public function testGetInfoDelegatesToInnerResponse(): void
    {
        $this->innerResponse->expects($this->once())->method('getInfo')
            ->with('response_headers')
            ->willReturn(['x-custom: value']);

        $this->assertSame(['x-custom: value'], $this->traceableResponse->getInfo('response_headers'));
    }

    public function testGetInnerResponseReturnsOriginalResponse(): void
    {
        $this->assertSame($this->innerResponse, $this->traceableResponse->getInnerResponse());
    }

    public function testDestructEndsSegmentIfNotAlreadyEnded(): void
    {
        $this->segment->expects($this->once())->method('end');

        // Destroy the response without calling any method
        unset($this->traceableResponse);
    }

    public function testDestructDoesNotEndSegmentTwice(): void
    {
        $this->innerResponse->method('getStatusCode')->willReturn(200);

        $this->segment->expects($this->once())->method('end');

        $this->traceableResponse->getStatusCode();
        unset($this->traceableResponse);
    }
}
