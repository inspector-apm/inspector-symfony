<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\Messenger;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Inspector\Symfony\Bundle\Messenger\MessengerMonitoringMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use stdClass;

use function interface_exists;

class MessengerMonitoringMiddlewareTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(StackInterface::class)) {
            self::markTestSkipped('symfony/messenger is not installed.');
        }
    }

    private Inspector&MockObject $inspector;
    private StackInterface&MockObject $stack;
    private MiddlewareInterface&MockObject $nextMiddleware;
    private MessengerMonitoringMiddleware $middleware;

    protected function setUp(): void
    {
        $this->inspector = $this->createMock(Inspector::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $this->stack->method('next')->willReturn($this->nextMiddleware);
        $this->middleware = new MessengerMonitoringMiddleware($this->inspector, []);
    }

    private function createEnvelope(?object $message = null, array $stamps = []): Envelope
    {
        return new Envelope($message ?? new stdClass(), $stamps);
    }

    // =========================================================================
    // Transaction & segment creation
    // =========================================================================

    public function testHandleStartsTransactionWhenNoneExists(): void
    {
        $envelope = $this->createEnvelope();

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->expects($this->once())->method('startTransaction')
            ->with('stdClass')
            ->willReturn($transaction);
        $transaction->expects($this->once())->method('setType')->with('message');

        // afterHandle adds context to the transaction (no segment)
        $this->inspector->method('transaction')->willReturn($transaction);
        $transaction->expects($this->once())->method('setResult')->with('success');

        // Sync message: no flush
        $this->inspector->expects($this->never())->method('flush');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    public function testHandleStartsSegmentWhenTransactionExists(): void
    {
        $envelope = $this->createEnvelope();

        $segment = $this->createMock(Segment::class);
        $segment->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(true);
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->expects($this->once())->method('startSegment')
            ->with('job.message', 'stdClass')
            ->willReturn($segment);

        // Segment is ended in the finally block
        $segment->expects($this->once())->method('end');

        // Sync message: no flush
        $this->inspector->expects($this->never())->method('flush');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    public function testHandleDoesNotStartSegmentWhenCanNotAddSegments(): void
    {
        $envelope = $this->createEnvelope();

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(true);
        $this->inspector->method('canAddSegments')->willReturn(false);
        $this->inspector->method('transaction')->willReturn($transaction);
        $this->inspector->expects($this->never())->method('startSegment');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    // =========================================================================
    // Skip / ignore
    // =========================================================================

    public function testHandleSkipsWhenNotRecording(): void
    {
        $envelope = $this->createEnvelope();

        $this->inspector->method('isRecording')->willReturn(false);
        $this->inspector->expects($this->never())->method('startTransaction');
        $this->inspector->expects($this->never())->method('startSegment');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $this->middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    public function testHandleSkipsIgnoredMessages(): void
    {
        $envelope = $this->createEnvelope();
        $middleware = new MessengerMonitoringMiddleware($this->inspector, ['stdClass']);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->expects($this->never())->method('startTransaction');
        $this->inspector->expects($this->never())->method('startSegment');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    public function testHandleSkipsIgnoredMessagesWithWildcard(): void
    {
        $envelope = $this->createEnvelope();
        $middleware = new MessengerMonitoringMiddleware($this->inspector, ['std*']);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->expects($this->never())->method('startTransaction');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $result = $middleware->handle($envelope, $this->stack);

        $this->assertSame($envelope, $result);
    }

    // =========================================================================
    // Handler context
    // =========================================================================

    public function testHandleAddsHandlerContextToTransaction(): void
    {
        $envelope = $this->createEnvelope();

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->method('startTransaction')->willReturn($transaction);
        $this->inspector->method('transaction')->willReturn($transaction);

        $handledStamp = new HandledStamp('result', 'App\Handler\MyHandler::__invoke');
        $envelopeWithStamps = $envelope->with($handledStamp);

        $this->nextMiddleware->method('handle')->willReturn($envelopeWithStamps);

        $transaction->expects($this->once())->method('addContext')
            ->with('Handlers', ['App\Handler\MyHandler::__invoke']);
        $transaction->expects($this->once())->method('setResult')->with('success');

        $this->middleware->handle($envelope, $this->stack);
    }

    public function testHandleAddsHandlerContextToSegment(): void
    {
        $envelope = $this->createEnvelope();

        $segment = $this->createMock(Segment::class);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(true);
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($segment);

        $handledStamp = new HandledStamp('result', 'App\Handler\MyHandler::__invoke');
        $envelopeWithStamps = $envelope->with($handledStamp);

        $this->nextMiddleware->method('handle')->willReturn($envelopeWithStamps);

        $segment->expects($this->once())->method('addContext')
            ->with('Handlers', ['App\Handler\MyHandler::__invoke']);

        $this->middleware->handle($envelope, $this->stack);
    }

    // =========================================================================
    // Flush (async)
    // =========================================================================

    public function testHandleFlushesForAsyncMessage(): void
    {
        $envelope = $this->createEnvelope(new stdClass(), [new ReceivedStamp('async')]);

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->method('startTransaction')->willReturn($transaction);
        $this->inspector->method('transaction')->willReturn($transaction);
        $this->inspector->expects($this->once())->method('flush');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $this->middleware->handle($envelope, $this->stack);
    }

    public function testHandleFlushesForAsyncMessageWithSegment(): void
    {
        $envelope = $this->createEnvelope(new stdClass(), [new ReceivedStamp('async')]);

        $segment = $this->createMock(Segment::class);
        $segment->method('addContext')->willReturnSelf();

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(true);
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($segment);
        $this->inspector->expects($this->once())->method('flush');

        $segment->expects($this->once())->method('end');

        $this->nextMiddleware->method('handle')->willReturnArgument(0);

        $this->middleware->handle($envelope, $this->stack);
    }

    // =========================================================================
    // Exception handling
    // =========================================================================

    public function testHandleReportsExceptionAndRethrows(): void
    {
        $envelope = $this->createEnvelope();
        $exception = new RuntimeException('Something went wrong');

        $transaction = $this->createMock(Transaction::class);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->method('startTransaction')->willReturn($transaction);
        $this->inspector->method('transaction')->willReturn($transaction);

        $this->inspector->expects($this->once())->method('reportException')
            ->with($exception, false);
        $transaction->expects($this->once())->method('setResult')->with('error');

        $this->nextMiddleware->method('handle')
            ->willThrowException($exception);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Something went wrong');

        $this->middleware->handle($envelope, $this->stack);
    }

    public function testHandleUnwrapsHandlerFailedException(): void
    {
        $envelope = $this->createEnvelope();

        $nested1 = new RuntimeException('Error 1');
        $nested2 = new InvalidArgumentException('Error 2');
        $exception = new HandlerFailedException($envelope, [$nested1, $nested2]);

        $transaction = $this->createMock(Transaction::class);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->method('startTransaction')->willReturn($transaction);
        $this->inspector->method('transaction')->willReturn($transaction);

        $reported = [];
        $this->inspector->expects($this->exactly(2))->method('reportException')
            ->with($this->callback(function (Throwable $e) use (&$reported) {
                $reported[] = $e;
                return true;
            }), false);

        $this->nextMiddleware->method('handle')
            ->willThrowException($exception);

        try {
            $this->middleware->handle($envelope, $this->stack);
        } catch (HandlerFailedException $e) {
            $this->assertSame($nested1, $reported[0]);
            $this->assertSame($nested2, $reported[1]);
        }
    }

    public function testHandleEndsSegmentEvenOnException(): void
    {
        $envelope = $this->createEnvelope();

        $segment = $this->createMock(Segment::class);
        $transaction = $this->createMock(Transaction::class);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(true);
        $this->inspector->method('canAddSegments')->willReturn(true);
        $this->inspector->method('startSegment')->willReturn($segment);
        $this->inspector->method('transaction')->willReturn($transaction);

        $this->nextMiddleware->method('handle')
            ->willThrowException(new RuntimeException('fail'));

        $segment->expects($this->once())->method('end');

        try {
            $this->middleware->handle($envelope, $this->stack);
        } catch (RuntimeException $e) {
            // expected
        }
    }

    public function testHandleFlushesEvenOnExceptionForAsyncMessage(): void
    {
        $envelope = $this->createEnvelope(new stdClass(), [new ReceivedStamp('async')]);

        $transaction = $this->createMock(Transaction::class);

        $this->inspector->method('isRecording')->willReturn(true);
        $this->inspector->method('hasTransaction')->willReturn(false);
        $this->inspector->method('startTransaction')->willReturn($transaction);
        $this->inspector->method('transaction')->willReturn($transaction);

        $this->inspector->expects($this->once())->method('flush');

        $this->nextMiddleware->method('handle')
            ->willThrowException(new RuntimeException('fail'));

        try {
            $this->middleware->handle($envelope, $this->stack);
        } catch (RuntimeException $e) {
            // expected
        }
    }
}
