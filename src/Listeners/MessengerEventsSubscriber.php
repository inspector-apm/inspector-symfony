<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

class MessengerEventsSubscriber implements EventSubscriberInterface
{
    use InspectorAwareTrait;

    /**
     * ConsoleEventsSubscriber constructor.
     * @param Inspector $inspector
     */
    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

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
        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($event->getReceiverName())
                ->addContext('worker', [
                    'message' => serialize($event->getEnvelope()->getMessage()),
                    'stamps' => serialize($event->getEnvelope()->all()),
                ]);
        } elseif ($this->inspector->canAddSegments()) {
            $this->segments[$event->getReceiverName()] = $this->inspector->startSegment('worker', $event->getReceiverName());
        }
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event)
    {
        $this->notifyUnexpectedError($event->getThrowable());

        if($this->inspector->hasTransaction() && $this->inspector->currentTransaction()->name === $event->getReceiverName()) {
            $this->inspector->currentTransaction()->setResult('error');
        } elseif(array_key_exists($event->getReceiverName(), $this->segments)) {
            $this->segments[$event->getReceiverName()]->end()->addContext('worker', [
                'message' => serialize($event->getEnvelope()->getMessage()),
                'stamps' => serialize($event->getEnvelope()->all()),
            ]);
        }
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event)
    {
        $this->segments[$event->getReceiverName()]->end();
    }
}
