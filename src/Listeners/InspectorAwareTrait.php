<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Throwable;

trait InspectorAwareTrait
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * @var Segment[]
     */
    protected $segments = [];

    /**
     * Checks if segments can be added
     */
    protected function canAddSegments(): bool
    {
        return $this->inspector->canAddSegments();
    }

    /**
     * Checks if transaction is needed
     */
    protected function needsTransaction(): bool
    {
        return $this->inspector->needTransaction();
    }

    /**
     * Be sure to start a transaction before report the exception.
     *
     * @throws \Exception
     */
    protected function startTransaction(string $name): ?Transaction
    {
        if ($this->inspector->needTransaction()) {
            $this->inspector->startTransaction($name);
        }

        return $this->inspector->transaction();
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

    protected function startSegment(string $type, string $label = null): Segment
    {
        $segment = $this->inspector->startSegment($type, $label);

        $this->segments[$label] = $segment;

        return $segment;
    }

    /**
     * Terminate the segment.
     *
     * @param string $label
     */
    protected function endSegment(string $label): void
    {
        if (!isset($this->segments[$label])) {
            return;
        }

        $this->segments[$label]->end();

        unset($this->segments[$label]);
    }
}
