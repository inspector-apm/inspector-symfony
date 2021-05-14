<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Transaction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

abstract class AbstractInspectorEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Inspector
     */
    protected $inspector;

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
}
