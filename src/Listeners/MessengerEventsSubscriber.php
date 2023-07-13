<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class MessengerEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * ConsoleEventsSubscriber constructor.
     *
     * @param Inspector $inspector
     */
    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @uses onWorkerMessageFailed
     * @uses onWorkerMessageHandled
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandled',
        ];
    }

    /**
     * Handle worker fail.
     *
     * @param WorkerMessageFailedEvent $event
     * @throws \Exception
     */
    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event)
    {
        if (! $this->inspector->isRecording()) {
            return;
        }

        // reportException will create a transaction if it doesn't exists.
        $this->inspector->reportException($event->getThrowable());
        $this->inspector->transaction()->setResult('error');
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
        if (!$this->inspector->hasTransaction()) {
            return;
        }

        $processedByStamps = $event->getEnvelope()->all(HandledStamp::class);
        $processedBy = [];

        /** @var HandledStamp $handlerStamp */
        foreach ($processedByStamps as $handlerStamp) {
            $processedBy[] = $handlerStamp->getHandlerName();
        }

        $this->inspector->transaction()
            ->addContext('Handlers', $processedBy)
            ->addContext('Envelope', \serialize($event->getEnvelope()));

        $this->inspector->flush();
    }
}
