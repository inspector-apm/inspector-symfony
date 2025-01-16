<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Filters;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MessengerEventsSubscriber implements EventSubscriberInterface
{
    protected Inspector $inspector;

    protected array $ignoreMessages;

    private TransportInterface $transport;

    protected $segments = [];

    public function __construct(
        Inspector $inspector,
        array $ignoreMessages,
        TransportInterface  $transport
    ) {
        $this->inspector = $inspector;
        $this->ignoreMessages = $ignoreMessages;
        $this->transport = $transport;
    }

    /**
     * @uses onWorkerMessageReceived
     * @uses onWorkerMessageFailed
     * @uses onWorkerMessageHandled
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onWorkerMessageReceived',
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
        ];
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event)
    {
        $class = get_class($event->getEnvelope()->getMessage());

        if (!$this->inspector->isRecording() || !$this->shouldBeMonitored($class)) {
            return;
        }

        if (!$this->inspector->hasTransaction() ) {
            $this->inspector->startTransaction($class)->setType('message');
        } elseif ($this->inspector->canAddSegments()) {
            $this->segments[$class] = $this->inspector->startSegment('message', $class);
        }
    }

    /**
     * Handle worker fail.
     *
     * @param WorkerMessageFailedEvent $event
     * @throws \Exception
     */
    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event)
    {
        $class = get_class($event->getEnvelope()->getMessage());

        if (! $this->inspector->isRecording() || !$this->shouldBeMonitored($class)) {
            return;
        }

        $this->inspector->reportException($event->getThrowable());

        if (\array_key_exists($class, $this->segments)) {
            $this->segments[$class]->end();
        }

        $this->inspector->transaction()->setResult('error');

        if ($this->isMessengerAsync()) {
            $this->inspector->flush();
        }
    }

    /**
     * MessageHandled.
     *
     * @param WorkerMessageHandledEvent $event
     * @throws \Exception
     */
    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event)
    {
        $class = get_class($event->getEnvelope()->getMessage());

        if (!$this->inspector->isRecording() || !$this->shouldBeMonitored($class)) {
            return;
        }

        $processedByStamps = $event->getEnvelope()->all(HandledStamp::class);
        $handlers = [];

        /** @var HandledStamp $handlerStamp */
        foreach ($processedByStamps as $handlerStamp) {
            $handlers[] = $handlerStamp->getHandlerName();
        }

        if (\array_key_exists($class, $this->segments)) {
            $this->segments[$class]
                ->addContext('Handlers', $handlers)
                ->end();
        } else {
            $this->inspector->transaction()->addContext('Handlers', $handlers);
        }

        if ($this->isMessengerAsync()) {
            $this->inspector->flush();
        }
    }

    /**
     * Determine if a message class should be monitored based on the package configuration.
     *
     * @param $message
     * @return bool
     */
    protected function shouldBeMonitored($message): bool
    {
        foreach ($this->ignoreMessages as $pattern) {
            if (Filters::matchWithWildcard($pattern, $message)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if messenger is configured as sync or async process.
     *
     * @return bool
     */
    protected function isMessengerAsync(): bool
    {
        return ! $this->transport instanceof InMemoryTransport;
    }
}
