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

    /** @var string[] Full FQCN of command class */
    protected $ignoredCommands;

    /**
     * ConsoleEventsSubscriber constructor.
     * @param Inspector $inspector
     * @param string[] $ignoredCommands Full FQCN of command class
     */
    public function __construct(Inspector $inspector, array $ignoredCommands)
    {
        $this->inspector = $inspector;
        $this->ignoredCommands = $ignoredCommands;
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
        $command = $event->getCommand();
        if (null === $command || $this->isIgnored(get_class($command))) {
            return;
        }
        $commandName = $command->getName();
        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($commandName)
                ->addContext('Command', [
                    'arguments' => $event->getInput()->getArguments(),
                    'options' => $event->getInput()->getOptions(),
                ]);
        }

        if ($this->inspector->canAddSegments()) {
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
        $command = $event->getCommand();
        if (null === $command || $this->isIgnored(get_class($command))) {
            return;
        }

        if ($this->inspector->canAddSegments()) {
            $this->inspector->currentTransaction()->setResult('error');
        }

        $this->notifyUnexpectedError($event->getError());
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();
        if (null === $command || $this->isIgnored(get_class($command))) {
            return;
        }

        $commandName = $command->getName();
        if($this->inspector->hasTransaction() && $this->inspector->currentTransaction()->name === $commandName) {
            $this->inspector->currentTransaction()->setResult($event->getExitCode() === 0 ? 'success' : 'error');
        }

        if(array_key_exists($commandName, $this->segments)) {
            $this->segments[$commandName]->end()->addContext('Command', [
                'exit_code' => $event->getExitCode(),
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ]);
        }
    }

    public function onConsoleSignal(ConsoleSignalEvent $event): void
    {
        $command = $event->getCommand();
        if (null === $command || $this->isIgnored(get_class($command))) {
            return;
        }

        if ($this->inspector->canAddSegments()) {
            $this->inspector->currentTransaction()->setResult('terminated');
        }
    }

    protected function isIgnored(string $fqcn): bool
    {
        return in_array($fqcn, $this->ignoredCommands);
    }
}
