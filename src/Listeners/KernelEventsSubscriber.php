<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Segment;
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

/**
 * @todo: exclude profiler monitoring
 * @todo: use trait for compatibility isMaster/isMain
 */
class KernelEventsSubscriber implements EventSubscriberInterface
{
    use InspectorAwareTrait;

    protected const SEGMENT_TYPE_PROCESS = 'process';
    protected const SEGMENT_CONTROLLER = 'controller';

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @uses onKernelController
     * @uses onKernelException
     * @uses onKernelFinishRequest
     * @uses onKernelPreControllerArguments
     * @uses onKernelPostControllerArguments
     * @uses onKernelRequest
     * @uses onKernelResponse
     * @uses onKernelTerminate
     * @uses onKernelView
     */
    public static function getSubscribedEvents(): array
    {
        // The higher the priority number, the earlier the method is called.
        $listeners = [
            KernelEvents::CONTROLLER => ['onKernelController', 9999],
            KernelEvents::CONTROLLER_ARGUMENTS => [
                ['onKernelPreControllerArguments', 9999],
                ['onKernelPostControllerArguments', -9999]
            ],
            KernelEvents::EXCEPTION => ['onKernelException', 9999],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', 9999],
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
            KernelEvents::VIEW => ['onKernelView', 9999],
            KernelEvents::TERMINATE => ['onKernelTerminate', -9999],
        ];

        return $listeners;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        $this->endSegment(KernelEvents::REQUEST);

        $this->startSegment(KernelEvents::CONTROLLER);
    }

    public function onKernelPreControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        $this->endSegment(KernelEvents::CONTROLLER);

        $this->startSegment(KernelEvents::CONTROLLER_ARGUMENTS);
    }

    public function onKernelPostControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        $this->endSegment(KernelEvents::CONTROLLER_ARGUMENTS);

        $this->startSegment(self::SEGMENT_CONTROLLER);
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
        $this->endSegment(self::SEGMENT_CONTROLLER);
        $this->endSegment(KernelEvents::REQUEST);
        $this->endSegment(KernelEvents::VIEW);

        $this->startSegment(KernelEvents::RESPONSE);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        $this->endSegment(KernelEvents::RESPONSE);
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
        if (!$event->isMasterRequest()){
            return;
        }

        $this->inspector->currentTransaction()->setResult($event->getResponse()->getStatusCode());
    }

    public function onKernelView(ViewEvent $event): void
    {
        if (!$event->isMasterRequest()){
            return;
        }

        $this->endSegment(self::SEGMENT_CONTROLLER);

        $this->startSegment(KernelEvents::VIEW);
    }
}
