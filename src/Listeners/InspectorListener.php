<?php


namespace Inspector\Symfony\Bundle\Listeners;


use Inspector\Inspector;
use Inspector\Models\Transaction;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Throwable;

class InspectorListener implements EventSubscriberInterface
{
    /**
     * @var Inspector
     */
    protected $inspector;

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @uses onKernelRequest
     * @uses onKernelController
     * @uses onKernelResponse
     * @uses onKernelException
     * @uses onConsoleStart
     */
    public static function getSubscribedEvents()
    {
        $listeners = [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse'],
            KernelEvents::EXCEPTION => ['onKernelException', 128],
            ConsoleEvents::COMMAND => ['onConsoleStart'],
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
     * Intercept an HTTP request.
     *
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $this->startTransaction(
            $event->getRequest()->getMethod() . ' ' . $event->getRequest()->getUri()
        );
    }

    /**
     * Ending transaction.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->inspector->isRecording()) {
            return;
        }

        $this->inspector->currentTransaction()->setResult($event->getResponse()->getStatusCode());
    }

    /**
     * Intercept a command execution.
     *
     * @throws \Exception
     */
    public function onConsoleStart(ConsoleCommandEvent $event): void
    {
        $this->startTransaction($event->getCommand()->getName());
    }

    /**
     * Handle an http kernel exception.
     *
     * @param GetResponseForExceptionEvent|ExceptionEvent $event
     *
     * @throws \Exception
     */
    public function onKernelException($event): void
    {

        // Compatibility with Symfony < 5 and Symfony >=5
        // The additional `method_exists` check is to prevent errors in Symfony 4.3
        // where the ExceptionEvent exists and is used but doesn't implement
        // the `getThrowable` method, which was introduced in Symfony 4.4
        if ($event instanceof ExceptionEvent && method_exists($event, 'getThrowable')) {

            $this->startTransaction(get_class($event->getThrowable()))->setResult('error');
            $this->notifyUnexpectedError($event->getThrowable());

        } elseif ($event instanceof GetResponseForExceptionEvent) {

            $this->startTransaction(get_class($event->getException()))->setResult('error');
            $this->notifyUnexpectedError($event->getException());

        }

        throw new \InvalidArgumentException('Invalid exception event.');
    }

    /**
     * Handle a console exception (used instead of ConsoleErrorEvent before
     * Symfony 3.3 and kept for backwards compatibility).
     *
     * @throws \Exception
     */
    public function onConsoleException(ConsoleExceptionEvent $event): void
    {
        $this->startTransaction($event->getCommand()->getName())->setResult('error');

        $this->notifyUnexpectedError($event->getException());
    }

    /**
     * Handle a console error.
     *
     * @throws \Exception
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $this->startTransaction($event->getCommand()->getName())->setResult('error');

        $this->notifyUnexpectedError($event->getError());
    }

    /**
     * Be sure to start a transaction before report the exception.
     *
     * @throws \Exception
     */
    protected function startTransaction(string $name): Transaction
    {
        if (!$this->inspector->isRecording()) {
            $this->inspector->startTransaction($name);
        }

        return $this->inspector->currentTransaction();
    }

    /**
     * Report unexpected error to inspection API.
     *
     * @throws \Exception
     */
    protected function notifyUnexpectedError(Throwable $throwable): void
    {
        $this->inspector->reportException($throwable, false);
    }
}
