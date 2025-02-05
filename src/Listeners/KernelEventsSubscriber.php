<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Filters;
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
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
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
    protected $routePath;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Security|null
     */
    protected $security;

    /**
     * @var TokenStorageInterface|null
     */
    protected $tokenStorage;

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
        ?Security $security,
        ?TokenStorageInterface $tokenStorage,
        array $ignoredRoutes
    ) {
        $this->inspector = $inspector;
        $this->router = $router;
        $this->security = $security;
        $this->tokenStorage = $tokenStorage;
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
        if (! $this->inspector->canAddSegments()) {
            return;
        }

        $this->endSegment(KernelEvents::REQUEST);

        $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, KernelEvents::CONTROLLER)
            ->addContext('Description', KernelEvents::CONTROLLER." event occurs once a controller was found for handling a request.");
    }

    public function onKernelPreControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (! $this->inspector->canAddSegments()) {
            return;
        }

        $this->endSegment(KernelEvents::CONTROLLER);

        $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, KernelEvents::CONTROLLER_ARGUMENTS)
            ->addContext('Description', KernelEvents::CONTROLLER_ARGUMENTS." event occurs once controller arguments have been resolved.");
    }

    public function onKernelPostControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (! $this->inspector->canAddSegments()) {
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

        $this->startSegment(self::SEGMENT_TYPE_CONTROLLER, $controllerLabel)
            ->addContext('Data', ['arguments' => $arguments]);
    }

    /**
     * Intercept an HTTP request.
     *
     * @throws \Exception
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->inspector->isRecording() || ! $this->isMasterMainRequest($event)) {
            return;
        }

        $request = $event->getRequest();

        // Retrieve the route pattern
        try {
            $routeInfo = $this->router->match($request->getPathInfo());
            $route = $this->router->getRouteCollection()->get($routeInfo['_route']);

            // If the route is not in the route collection, $route could be null.
            if ($route instanceof Route) {
                $this->routePath = $route->getPath();
            } else {
                $this->routePath = $request->getPathInfo();
            }

        } catch (\Throwable $exception) {
            $this->routePath = $request->getPathInfo();
        }

        if (!$this->isRequestEligibleForInspection($this->routePath)) {
            return;
        }

        $this->startTransaction($event->getRequest()->getMethod() . ' /' . \trim($this->routePath, '/'))
            ->markAsRequest();

        $this->startSegment(self::SEGMENT_TYPE_PROCESS, KernelEvents::REQUEST);
    }

    public function setAuthUserInfo(RequestEvent $event): void
    {
        if (! $this->inspector->hasTransaction()) {
            return;
        }

        if (null !== $this->tokenStorage) {
            // Symfony Security Bundle 7+
            if (null === $token = $this->tokenStorage->getToken()) {
                return;
            }

            if (null === $user = $token->getUser()) {
                return;
            }
        } elseif (null !== $this->security) {
            // Symfony Security Bundle <7
            // Symfony\Component\Security\Core\Security exists since 7.
            $user = $this->security->getUser();
        } else {
            $user = null;
        }

        if ($user) {
            $transaction = $this->inspector->transaction();
            if (null === $transaction) {
                return;
            }

            $transaction->withUser($user->getUserIdentifier());
        }
    }

    /**
     * Ending transaction.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->inspector->canAddSegments() || !$this->isMasterMainRequest($event)) {
            return;
        }

        // End segment based on a kernel event type
        $controllerLabel = $this->controllerLabel($event);
        if ($controllerLabel) {
            $this->endSegment($controllerLabel);
        }
        $this->endSegment(KernelEvents::REQUEST);
        $this->endSegment(KernelEvents::VIEW);

        $response = $event->getResponse();

        $segment = $this->startSegment(self::SEGMENT_TYPE_PROCESS, KernelEvents::RESPONSE);
        $segment->addContext('Response', [
            'headers' => $response->headers->all(),
            'protocolVersion' => $response->getProtocolVersion(),
            'statusCode' => $response->getStatusCode(),
            'charset' => $response->getCharset(),
        ]);
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if ($this->inspector->canAddSegments() && $this->isMasterMainRequest($event)) {
            $this->endSegment(KernelEvents::RESPONSE);
        }
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
        if (!$this->inspector->isRecording()) {
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

        $this->inspector->reportException($exception, false);
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->inspector->hasTransaction()){
            $this->inspector->transaction()->setResult($event->getResponse()->getStatusCode());
        }
    }

    public function onKernelView(ViewEvent $event): void
    {
        if (!$this->inspector->canAddSegments() || $this->isMasterMainRequest($event)){
            return;
        }

        $controllerLabel = $event->getRequest()->attributes->get('_controller');

        if ($controllerLabel) {
            $this->endSegment($controllerLabel);
        }

        $this->startSegment(self::SEGMENT_TYPE_TEMPLATE, KernelEvents::VIEW);
    }

    protected function isRequestEligibleForInspection($path): bool
    {
        foreach ($this->ignoredRoutes as $pattern) {
            if (Filters::matchWithWildcard($pattern, $path)) {
                return false;
            }
        }

        return true;
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

    private function isMasterMainRequest(KernelEvent $event): bool
    {
        return (method_exists($event, 'isMainRequest') && $event->isMainRequest())
        || (method_exists($event, 'isMasterRequest') && $event->isMasterRequest());
    }
}
