<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleEventsSubscriber implements EventSubscriberInterface
{
    use InspectorAwareTrait;

    protected const SEGMENT_TYPE_PROCESS = 'process';
    protected const LABEL_COMMAND_EXECUTION = 'Command Execution';

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * @uses onConsoleStart
     * @uses onConsoleError
     * @uses onConsoleTerminate
     * @uses onConsoleSignal
     */
    public static function getSubscribedEvents(): array
    {
        // The higher the priority number, the earlier the method is called.
        $listeners = [
            ConsoleEvents::COMMAND => ['onConsoleStart', 9999],
            ConsoleEvents::ERROR => ['onConsoleError', 128],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', 0],
            ConsoleEvents::SIGNAL => ['onConsoleSignal', 0],
        ];

        return $listeners;
    }


    /**
     * Intercept a command execution.
     *
     * @throws \Exception
     */
    public function onConsoleStart(ConsoleCommandEvent $event): void
    {
        $commandName = $event->getCommand()->getName();
        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($commandName)
                ->addContext('Command', [
                    'arguments' => $event->getInput()->getArguments(),
                    'options' => $event->getInput()->getOptions(),
                ]);
        } elseif ($this->inspector->canAddSegments()) {
            $this->segments[$commandName] = $this->inspector->startSegment('command', $commandName);
        }
    }

    /**
     * Handle a console error.
     *
     * @throws \Exception
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        if ($this->inspector->canAddSegments()) {
            $this->inspector->currentTransaction()->setResult('error');
        }

        $this->notifyUnexpectedError($event->getError());
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $commandName = $event->getCommand()->getName();
        if($this->inspector->hasTransaction() && $this->inspector->currentTransaction()->name === $commandName) {
            $this->inspector->currentTransaction()->setResult($event->exitCode === 0 ? 'success' : 'error');
        } elseif(array_key_exists($commandName, $this->segments)) {
            $this->segments[$commandName]->end()->addContext('Command', [
                'exit_code' => $event->getExitCode(),
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ]);
        }
    }

    public function onConsoleSignal(ConsoleSignalEvent $event): void
    {
        if ($this->inspector->canAddSegments()) {
            $this->inspector->currentTransaction()->setResult('terminated');
        }
    }
}
