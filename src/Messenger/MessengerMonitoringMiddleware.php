<?php

namespace Inspector\Symfony\Bundle\Messenger;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Symfony\Bundle\Filters;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerMonitoringMiddleware implements MiddlewareInterface
{
    protected Inspector $inspector;

    protected array $ignoreMessages;

    protected TransportInterface $transport;

    protected ?Segment $segment = null;

    public function __construct(
        Inspector $inspector,
        array $ignoreMessages,
        TransportInterface  $transport
    ) {
        $this->inspector = $inspector;
        $this->ignoreMessages = $ignoreMessages;
        $this->transport = $transport;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        try {
            // Before handling the message in sync mode
            $this->beforeHandle($message);

            // Handle the message
            $envelope = $stack->next()->handle($envelope, $stack);

            // After handling the message in sync mode
            $this->afterHandle($message, $envelope->all(HandledStamp::class));

            return $envelope;
        } catch (\Throwable $error) {
            $this->errorHandle($message, $error);
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
    protected function beforeHandle($message): void
    {
        $class = get_class($message);

        if (!$this->inspector->isRecording() || $this->shouldBeIgnored($class)) {
            return;
        }

        if (!$this->inspector->hasTransaction()) {
            $this->inspector->startTransaction($class)->setType('message');
        } elseif ($this->inspector->canAddSegments()) {
            $this->segment = $this->inspector->startSegment('message', $class);
        }
    }

    protected function afterHandle($message, array $handlers): void
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

    protected function errorHandle($message, \Throwable $error): void
    {
        $this->inspector->reportException($error);
        $this->inspector->transaction()->setResult('error');
    }

    /**
     * Determine if a message class should be monitored based on the package configuration.
     *
     * @param $message
     * @return bool
     */
    protected function shouldBeIgnored($message): bool
    {
        foreach ($this->ignoreMessages as $pattern) {
            if (Filters::matchWithWildcard($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if messenger is configured as sync or async process.
     *
     * @return bool
     */
    protected function isAsync(Envelope $envelope): bool
    {
        return $envelope->last(ReceivedStamp::class) !== null;
    }
}
