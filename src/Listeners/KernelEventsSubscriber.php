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
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

/**
 * @todo: use trait for compatibility isMaster/isMain
 */
class KernelEventsSubscriber implements EventSubscriberInterface
{
    use InspectorAwareTrait;

    protected const SEGMENT_TYPE_CONTROLLER = 'controller';
    protected const SEGMENT_TYPE_PROCESS = 'process';
    protected const SEGMENT_TYPE_TEMPLATE = 'template';

    /**
     * @var string[]
     */
    protected $ignoredRoutes = [];

    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Security
     */
    protected $security;

    /**
     * KernelEventsSubscriber constructor.
     *
     * @param Inspector $inspector
     * @param RouterInterface $router
     * @param Security $security
     * @param array $ignoredRoutes
     */
    public function __construct(
        Inspector $inspector,
        RouterInterface $router,
        Security $security,
        array $ignoredRoutes
    ) {
        $this->inspector = $inspector;
        $this->router = $router;
        $this->security = $security;
        $this->ignoredRoutes = $ignoredRoutes;
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
     * @uses setAuthUserInfo
     */
    public static function getSubscribedEvents(): array
    {
        // The higher the priority number, the earlier the method is called.
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 9999],
            KernelEvents::CONTROLLER_ARGUMENTS => [
                ['onKernelPreControllerArguments', 9999],
                ['onKernelPostControllerArguments', -9999]
            ],
            KernelEvents::EXCEPTION => ['onKernelException', 9999],
            KernelEvents::FINISH_REQUEST => ['onKernelFinishRequest', 9999],
            KernelEvents::REQUEST => [['onKernelRequest', 9999], ['setAuthUserInfo', 30]],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
            KernelEvents::VIEW => ['onKernelView', 9999],
            KernelEvents::TERMINATE => ['onKernelTerminate', -9999],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        $this->endSegment(KernelEvents::REQUEST);

        $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, KernelEvents::CONTROLLER);
    }

    public function onKernelPreControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        $this->endSegment(KernelEvents::CONTROLLER);

        $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, KernelEvents::CONTROLLER_ARGUMENTS);
    }

    public function onKernelPostControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        $this->endSegment(KernelEvents::CONTROLLER_ARGUMENTS);

        $controllerLabel = $this->controllerLabel($event);

        $arguments = [];
        foreach ($event->getArguments() as $argument) {
            if (\is_object($argument)) {
                $args = ['class' => \get_class($argument)];
                if (\method_exists($argument, 'getId')) {
                    $args['id'] = $argument->getId();
                }
                $arguments[] = $args;
            } else {
                $arguments[] = $argument;
            }
        }
        $segment = $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, $controllerLabel);
        $segment->addContext($controllerLabel, ['arguments' => $arguments]);
    }

    /**
     * Intercept an HTTP request.
     *
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        try {
            $routeInfo = $this->router->match($request->getPathInfo());
            $this->routeName = $routeInfo['_route'];
        } catch (\Throwable $exception) {
            $this->routeName = $request->getPathInfo();
        }

        if (!$this->isRequestEligibleForInspection($event)) {
            return;
        }

        $this->startTransaction($event->getRequest()->getMethod() . ' /' . \trim($this->routeName, '/'));
        $this->startSegment(self::SEGMENT_TYPE_PROCESS, KernelEvents::REQUEST);
    }

    public function setAuthUserInfo(RequestEvent $event): void
    {
        if (! $this->inspector->isRecording()) {
            return;
        }

        $user = $this->security->getUser();

        if ($user) {
            $this->inspector->currentTransaction()->withUser($user->getUsername());
        }
    }

    /**
     * Ending transaction.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        if (!$this->inspector->isRecording()) {
            return;
        }

        /** @var Segment $segment */
        $controllerLabel = $this->controllerLabel($event);
        if ($controllerLabel) {
            $this->endSegment($controllerLabel);
        }

        $this->endSegment(KernelEvents::REQUEST);
        $this->endSegment(KernelEvents::VIEW);
        $response = $event->getResponse();
        $segment = $this->startSegment(self::SEGMENT_TYPE_PROCESS, KernelEvents::RESPONSE);
        $segment->addContext(KernelEvents::RESPONSE, ['response' => [
            'headers' => $response->headers->all(),
            'protocolVersion' => $response->getProtocolVersion(),
            'statusCode' => $response->getStatusCode(),
            'charset' => $response->getCharset(),
        ]]);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
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
        if (! $this->inspector->isRecording()) {
            return;
        }
        // Compatibility with Symfony < 5 and Symfony >=5
        // The additional `method_exists` check is to prevent errors in Symfony 4.3
        // where the ExceptionEvent exists and is used but doesn't implement
        // the `getThrowable` method, which was introduced in Symfony 4.4
        if ($event instanceof ExceptionEvent && \method_exists($event, 'getThrowable')) {
            $exception = $event->getThrowable();
        } elseif ($event instanceof GetResponseForExceptionEvent) {
            $exception = $event->getException();
        } else {
            throw new \LogicException('Invalid exception event.');
        }

        $this->startTransaction(get_class($exception))->setResult('error');
        $this->notifyUnexpectedError($exception);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        $this->inspector->currentTransaction()->setResult($event->getResponse()->getStatusCode());
    }

    public function onKernelView(ViewEvent $event): void
    {
        if (!$this->isRequestEligibleForInspection($event)){
            return;
        }

        $controllerLabel = $event->getRequest()->attributes->get('_controller');

        if ($controllerLabel) {
            $this->endSegment($controllerLabel);
        }

        $this->startSegment(self::SEGMENT_TYPE_TEMPLATE, KernelEvents::VIEW);
    }

    // TODO: use trait for compatibility isMaster/isMain
    // TODO: track sub requests?
    protected function isRequestEligibleForInspection(KernelEvent $event): bool
    {
        $route = $event->getRequest()->attributes->get('_route') ?: $this->routeName;

        return $event->isMasterRequest()
            && !\in_array($route, $this->ignoredRoutes)
            && $this->inspector->isRecording();
    }

    private function controllerLabel(KernelEvent $event): ?string
    {
        $controllerLabel = $event->getRequest()->attributes->get('_controller');

        if (is_null($controllerLabel)) {
            return null;
        }

        if (is_string($controllerLabel)) {
            return $controllerLabel;
        }

        if (is_array($controllerLabel)) {
            return implode('::', $controllerLabel);
        }

        return '_controller';
    }
}
