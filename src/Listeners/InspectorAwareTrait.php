<?php


namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Throwable;

trait InspectorAwareTrait
{
    /** @var Inspector */
    protected $inspector;

    /** @var Segment[] */
    protected $segments = [];

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

    protected function startSegment(string $label): void
    {
        $segment = $this->inspector->startSegment(self::SEGMENT_TYPE_PROCESS, $label);

        $this->segments[$label] = $segment;
    }

    protected function endSegment(string $label): void
    {
        if (!isset($this->segments[$label])) {
            return;
        }

        $this->segments[$label]->end();

        unset($this->segments[$label]);
    }
}
