<?php

namespace Inspector\Symfony\Bundle\Messenger;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Symfony\Bundle\Filters;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class MessengerMonitoringMiddleware implements MiddlewareInterface
{
    protected Inspector $inspector;

    protected array $ignoreMessages;

    protected ?Segment $segment = null;

    public function __construct(
        Inspector $inspector,
        array $ignoreMessages
    ) {
        $this->inspector = $inspector;
        $this->ignoreMessages = $ignoreMessages;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $class = get_class($envelope->getMessage());

        if (!$this->inspector->isRecording() || $this->shouldBeIgnored($class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            // Before handling the message
            $this->beforeHandle($class);

            // Handle the message
            $envelope = $stack->next()->handle($envelope, $stack);

            // After handling the message
            $this->afterHandle($envelope->all(HandledStamp::class));

            return $envelope;
        } catch (\Throwable $error) {
            $this->errorHandle($error);
            throw $error;
        } finally {
            if ($this->segment instanceof Segment) {
                $this->segment->end();
            }
            if ($this->isAsync($envelope)) {
                $this->inspector->flush();
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function beforeHandle($class): void
    {
        if (!$this->inspector->hasTransaction()) {
            $this->inspector->startTransaction($class)->setType('message');
        } elseif ($this->inspector->canAddSegments()) {
            $this->segment = $this->inspector->startSegment('message', $class);
        }
    }

    protected function afterHandle(array $handlers): void
    {
        $h = [];
        /** @var HandledStamp $handlerStamp */
        foreach ($handlers as $handlerStamp) {
            $h[] = $handlerStamp->getHandlerName();
        }

        if ($this->segment instanceof Segment) {
            $this->segment->addContext('Handlers', $h);
        } else {
            $this->inspector->transaction()
                ->addContext('Handlers', $h)
                ->setResult('success');
        }
    }

    protected function errorHandle(\Throwable $exception): void
    {
        if ($exception instanceof WrappedExceptionsInterface) {
            $exception = $exception->getWrappedExceptions();
        } elseif ($exception instanceof HandlerFailedException && \method_exists($exception, 'getNestedExceptions')) {
            $exception = $exception->getNestedExceptions();
        } elseif ($exception instanceof DelayedMessageHandlingException && \method_exists($exception, 'getExceptions')) {
            $exception = $exception->getExceptions();
        }

        if (\is_array($exception)) {
            foreach ($exception as $nestedException) {
                $this->errorHandle($nestedException);
            }

            return;
        }

        $this->inspector->reportException($exception, false);
        $this->inspector->transaction()->setResult('error');
    }

    /**
     * Determine if a message class should be monitored based on the package configuration.
     *
     * @param $class
     * @return bool
     */
    protected function shouldBeIgnored($class): bool
    {
        foreach ($this->ignoreMessages as $pattern) {
            if (Filters::matchWithWildcard($pattern, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if messenger is configured as sync or async process.
     *
     * @param Envelope $envelope
     * @return bool
     */
    protected function isAsync(Envelope $envelope): bool
    {
        return $envelope->last(ReceivedStamp::class) !== null;
    }
}
