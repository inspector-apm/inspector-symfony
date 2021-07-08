<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\HandledStamp;

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
        $this->startTransaction(get_class($event->getEnvelope()->getMessage()));
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event)
    {
        $this->notifyUnexpectedError($event->getThrowable());

        if ($this->inspector->hasTransaction()) {
            $this->inspector->currentTransaction()->setResult('error');
            $this->inspector->flush();
        }
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event)
    {
        $processedByStamps = $event->getEnvelope()->all(HandledStamp::class);
        $processedBy = [];
        /** @var HandledStamp $handlerStamp */
        foreach ($processedByStamps as $handlerStamp) {
            $processedBy = $handlerStamp->getHandlerName();
        }
        $this->inspector->currentTransaction()->addContext('Handled By', $processedBy);
        $this->inspector->currentTransaction()->addContext('Envelope', serialize($event->getEnvelope()));

        $this->inspector->flush();
    }
}
