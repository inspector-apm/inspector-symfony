<?php

namespace Inspector\Symfony\Bundle\Doctrine\Middleware\V3;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Inspector\Symfony\Bundle\Doctrine\Middleware\InspectorSQLSegmentTracer;

/**
 * Connection class for Doctrine DBAL3
 *
 * @internal
 */
class Connection extends AbstractConnectionMiddleware
{
    /** @var InspectorSQLSegmentTracer */
    protected $inspectorSQLSegmentTracer;

    private int $nestingLevel = 0;

    public function __construct(
        ConnectionInterface $connection,
        InspectorSQLSegmentTracer $inspectorSQLSegmentTracer
    ) {
        parent::__construct($connection);

        $this->inspectorSQLSegmentTracer = $inspectorSQLSegmentTracer;
    }

    public function prepare(string $sql): Statement
    {
        return new Statement(
            parent::prepare($sql),
            $this->inspectorSQLSegmentTracer,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $this->inspectorSQLSegmentTracer->startQuery($sql);

        try {
            return parent::query($sql);
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }

    public function exec(string $sql): int
    {
        $this->inspectorSQLSegmentTracer->startQuery($sql);

        try {
            return parent::exec($sql);
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }

    public function beginTransaction(): void
    {
        if (1 === ++$this->nestingLevel) {
            $this->inspectorSQLSegmentTracer->startQuery('START TRANSACTION');
        }

        try {
            parent::beginTransaction();
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }

    public function commit(): void
    {
        if (1 === $this->nestingLevel--) {
            $this->inspectorSQLSegmentTracer->startQuery('COMMIT');
        }

        try {
            parent::commit();
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }

    public function rollBack(): void
    {
        if (1 === $this->nestingLevel--) {
            $this->inspectorSQLSegmentTracer->startQuery('ROLLBACK');
        }

        try {
            parent::rollBack();
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }
}
