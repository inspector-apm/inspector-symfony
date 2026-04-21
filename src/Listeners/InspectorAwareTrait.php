<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Listeners;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Exception;

trait InspectorAwareTrait
{
    protected Inspector $inspector;

    /**
     * @var Segment[]
     */
    protected array $segments = [];

    /**
     * Be sure to start a transaction before report the exception.
     *
     * @throws Exception
     */
    protected function startTransaction(string $name): ?Transaction
    {
        if ($this->inspector->needTransaction()) {
            return $this->inspector->startTransaction($name);
        }

        return $this->inspector->transaction();
    }

    protected function startSegment(string $type, ?string $label = null): Segment
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
