<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Symfony\Bundle\Filters;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleEventsSubscriber implements EventSubscriberInterface
{
    use InspectorAwareTrait;

    /**
     * @var string[] command names
     */
    protected $ignoredCommands;

    /**
     * ConsoleEventsSubscriber constructor.
     *
     * @param Inspector $inspector
     * @param string[] $ignoredCommands command names
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
        ];

        if (defined('Symfony\Component\Console::CONSOLE_SIGNAL')) {
            $listeners[ConsoleEvents::SIGNAL] = ['onConsoleSignal', 0];
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
        $command = $event->getCommand();

        if (null === $command || $this->isIgnored($command)) {
            return;
        }

        $commandName = $command->getName();

        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($commandName)
                ->setType('command')
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
        $command = $event->getCommand();

        if (null === $command || $this->isIgnored($command) || ! $this->inspector->isRecording()) {
            return;
        }

        $this->inspector->reportException($event->getError(), false);
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $command = $event->getCommand();

        if (null === $command || $this->isIgnored($command)) {
            return;
        }

        $commandName = $command->getName();

        if($this->inspector->hasTransaction() && $this->inspector->transaction()->name === $commandName) {
            $this->inspector->transaction()->setResult($event->getExitCode() === 0 ? 'success' : 'error');
        } elseif(\array_key_exists($commandName, $this->segments)) {
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

        if (null === $command || $this->isIgnored($command)) {
            return;
        }

        if ($this->inspector->canAddSegments()) {
            $this->inspector->transaction()->setResult('terminated');
        }
    }

    protected function isIgnored(Command $command): bool
    {
        foreach ($this->ignoredCommands as $pattern) {
            if (Filters::matchWithWildcard($pattern, $command->getName())) {
                return true;
            }
        }

        return false;
    }
}
