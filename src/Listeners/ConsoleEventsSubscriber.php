<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

class ConsoleEventsSubscriber extends AbstractInspectorEventSubscriber
{
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
     * @uses onConsoleException
     * @uses onConsoleError
     */
    public static function getSubscribedEvents(): array
    {
        // The higher the priority number, the earlier the method is called.

        $listeners = [
            ConsoleEvents::COMMAND => ['onConsoleStart', 9999],
        ];

        // @todo: investigate and clean up
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
     * Intercept a command execution.
     *
     * @throws \Exception
     */
    public function onConsoleStart(ConsoleCommandEvent $event): void
    {
        $this->startTransaction($event->getCommand()->getName());
    }


    /**
     * Handle a console exception (used instead of ConsoleErrorEvent before
     * Symfony 3.3 and kept for backwards compatibility).
     *
     * @throws \Exception
     */
    public function onConsoleException(ConsoleErrorEvent $event): void
    {
        $this->startTransaction($event->getCommand()->getName())->setResult('error');

        $this->notifyUnexpectedError($event->getError());
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
}
