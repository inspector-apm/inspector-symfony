<?php


namespace Inspector\Symfony\Listener;


use Inspector\Inspector;
use Inspector\Models\Transaction;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class InspectorListener implements EventSubscriberInterface
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * InspectorListener constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->inspector = $container->get('inspector');
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        $listeners = [
            //KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::EXCEPTION => ['onKernelException', 128],
        ];

        // Added ConsoleEvents in Symfony 2.3
        if (class_exists(ConsoleEvents::class)) {
            // Added with ConsoleEvents::ERROR in Symfony 3.3 to deprecate ConsoleEvents::EXCEPTION
            if (class_exists(ConsoleErrorEvent::class)) {
                $listeners[ConsoleEvents::ERROR] = ['onConsoleError', 128];
            } else {
                $listeners[ConsoleEvents::EXCEPTION] = ['onConsoleException', 128];
            }
        }

        return $listeners;
    }

    /**
     * Handle an http kernel exception.
     *
     * @param GetResponseForExceptionEvent|ExceptionEvent $event
     * @return void
     * @throws \Exception
     */
    public function onKernelException($event)
    {

        // Compatibility with Symfony < 5 and Symfony >=5
        // The additional `method_exists` check is to prevent errors in Symfony 4.3
        // where the ExceptionEvent exists and is used but doesn't implement
        // the `getThrowable` method, which was introduced in Symfony 4.4
        if ($event instanceof ExceptionEvent && method_exists($event, 'getThrowable')) {
            $this->startTransaction(get_class($event->getThrowable()));
            $this->notifyUnexpectedError($event->getThrowable());
        } elseif ($event instanceof GetResponseForExceptionEvent) {
            $this->startTransaction(get_class($event->getException()));
            $this->notifyUnexpectedError($event->getException());
        } else {
            throw new \InvalidArgumentException('Invalid exception event.');
        }
    }

    /**
     * Handle a console exception (used instead of ConsoleErrorEvent before
     * Symfony 3.3 and kept for backwards compatibility).
     *
     * @param \Symfony\Component\Console\Event\ConsoleExceptionEvent $event
     * @return void
     * @throws \Exception
     */
    public function onConsoleException(ConsoleExceptionEvent $event)
    {
        $this->startTransaction($event->getCommand()->getName());

        $this->notifyUnexpectedError($event->getException());
    }

    /**
     * Handle a console error.
     *
     * @param \Symfony\Component\Console\Event\ConsoleErrorEvent $event
     * @return void
     * @throws \Exception
     */
    public function onConsoleError(ConsoleErrorEvent $event)
    {
        $this->startTransaction($event->getCommand()->getName());

        $this->notifyUnexpectedError($event->getError());
    }

    /**
     * Be sure to start a transaction before report the exception.
     *
     * @param string $name
     * @return Transaction
     * @throws \Exception
     */
    protected function startTransaction($name)
    {
        if (!$this->inspector->isRecording()) {
            $this->inspector->startTransaction($name);
        }

        return $this->inspector->currentTransaction();
    }

    /**
     * Report unexpected error to inspection API.
     *
     * @param $throwable
     */
    protected function notifyUnexpectedError($throwable)
    {
        $this->inspector->reportException($throwable, false);
    }
}
