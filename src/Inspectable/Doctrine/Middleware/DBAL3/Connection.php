<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware\DBAL3;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware\InspectorSQLLogger;

/**
 * @internal
 */
class Connection extends AbstractConnectionMiddleware
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

    private int $nestingLevel = 0;

    public function __construct(
        ConnectionInterface $connection,
        InspectorSQLLogger $inspectorSQLLogger
    ) {
        parent::__construct($connection);
        $this->inspectorSQLLogger = $inspectorSQLLogger;
    }

    public function prepare(string $sql): Statement
    {
        return new Statement(
            parent::prepare($sql),
            $this->inspectorSQLLogger,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $this->inspectorSQLLogger->startQuery($sql);

        try {
            return parent::query($sql);
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function exec(string $sql): int
    {
        $this->inspectorSQLLogger->startQuery($sql);

        try {
            return parent::exec($sql);
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function beginTransaction(): bool
    {
        if (1 === ++$this->nestingLevel) {
            $this->inspectorSQLLogger->startQuery('START TRANSACTION');
        }

        try {
            return parent::beginTransaction();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function commit(): bool
    {
        if (1 === $this->nestingLevel--) {
            $this->inspectorSQLLogger->startQuery('COMMIT');
        }

        try {
            return parent::commit();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function rollBack(): bool
    {
        if (1 === $this->nestingLevel--) {
            $this->inspectorSQLLogger->startQuery('ROLLBACK');
        }

        try {
            return parent::rollBack();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }
}
