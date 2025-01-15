<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Filters;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class MessengerEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * @var array
     */
    protected $ignoreMessages;

    /**
     * @var array
     */
    protected $segments = [];

    /**
     * ConsoleEventsSubscriber constructor.
     *
     * @param Inspector $inspector
     * @param array $ignoreMessages
     */
    public function __construct(Inspector $inspector, array $ignoreMessages = [])
    {
        $this->inspector = $inspector;
        $this->ignoreMessages = $ignoreMessages;
    }

    /**
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

        // todo: if we can know if it's sync or async we can call flush only for async.
        $this->inspector->flush();
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
            $this->inspector->transaction()
                ->addContext('Handlers', $handlers);
        }

        // todo: if we can know if it's sync or async we can call flush only for async.
        $this->inspector->flush();
    }

    protected function shouldBeMonitored($message): bool
    {
        foreach ($this->ignoreMessages as $pattern) {
            if (Filters::matchWithWildcard($pattern, $message)) {
                return false;
            }
        }

        return true;
    }
}
