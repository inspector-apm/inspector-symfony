<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Throwable;

class InspectorListener implements EventSubscriberInterface
{
    public const SEGMENT_TYPE_PROCESS = 'process';
    public const CONTROLLER = 'controller';

    /**
     * @var Inspector
     */
    protected $inspector;

    protected $segments = [];

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @uses onConsoleStart
     * @uses onKernelController
     * @uses onKernelException
     * @uses onKernelFinishRequest
     * @uses onKernelRequest
     * @uses onKernelResponse
     *
     * @todo: add proper priorities
     */
    public static function getSubscribedEvents()
    {
        $listeners = [
            ConsoleEvents::COMMAND => ['onConsoleStart'],

            KernelEvents::CONTROLLER => ['onKernelController'],
            KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelControllerArguments'],
            KernelEvents::EXCEPTION => ['onKernelException', 128],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest'],
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse'],
            KernelEvents::VIEW => ['onKernelView'],
            KernelEvents::TERMINATE => ['onKernelTerminate'],
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

    public function onKernelController(ControllerEvent $event): void
    {
        $this->endSegment(KernelEvents::REQUEST);

        $this->startSegment(KernelEvents::CONTROLLER);
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $this->endSegment(KernelEvents::CONTROLLER);

        $this->startSegment(self::CONTROLLER);
    }


    /**
     * Intercept an HTTP request.
     *
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        // TODO: use trait for compatibility
        // TODO: track sub requests?
        if (!$event->isMasterRequest()){
            return;
        }

        $this->startTransaction(
            $event->getRequest()->getMethod() . ' ' . $event->getRequest()->getUri()
        );

        $this->startSegment(KernelEvents::REQUEST);
    }

    /**
     * Ending transaction.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        if (!$this->inspector->isRecording()) {
            return;
        }

        //TODO: $this->inspector->endSegment(self::SEGMENT_TYPE_PROCESS, KernelEvents::REQUEST);
        /** @var Segment $segment */
        $this->endSegment(KernelEvents::CONTROLLER);
        $this->endSegment(KernelEvents::CONTROLLER_ARGUMENTS);
        $this->endSegment(KernelEvents::REQUEST);
        $this->endSegment(KernelEvents::VIEW);
        $this->endSegment(self::CONTROLLER);
        $this->startSegment(KernelEvents::RESPONSE);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $this->endSegment(KernelEvents::CONTROLLER);
        $this->endSegment(KernelEvents::CONTROLLER_ARGUMENTS);
        $this->endSegment(KernelEvents::REQUEST);
        $this->endSegment(KernelEvents::VIEW);

        $this->endSegment(KernelEvents::RESPONSE);
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

        } else {
            throw new \LogicException('Invalid exception event.');
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->inspector->currentTransaction()->setResult($event->getResponse()->getStatusCode());
    }

    public function onKernelView(ViewEvent $event): void
    {
        $this->endSegment(KernelEvents::CONTROLLER);
        $this->endSegment(KernelEvents::CONTROLLER_ARGUMENTS);
        $this->endSegment(self::CONTROLLER);

        $this->startSegment(KernelEvents::VIEW);
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
        if ($this->inspector->needTransaction()) {
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

    /**
     * Workaround method, should be removed after
     * @link https://github.com/inspector-apm/inspector-php/issues/9
     */
    private function startSegment(string $label): void
    {
        $segment = $this->inspector->startSegment(self::SEGMENT_TYPE_PROCESS, $label);

        $this->segments[$label] = $segment;
    }

    /**
     * Workaround method, should be removed after
     * @link https://github.com/inspector-apm/inspector-php/issues/9
     */
    private function endSegment(string $label): void
    {
        if (!isset($this->segments[$label])) {
            return;
        }

        $this->segments[$label]->end();

        unset($this->segments[KernelEvents::REQUEST]);
    }
}
