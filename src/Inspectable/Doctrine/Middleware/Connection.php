<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;

/**
 * @internal
 */
class Connection extends AbstractConnectionMiddleware
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

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
            $affectedRows = parent::exec($sql);
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }

        return $affectedRows;
    }

    public function beginTransaction(): void
    {
        $this->inspectorSQLLogger->startQuery('START TRANSACTION');

        try {
            parent::beginTransaction();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function commit(): void
    {
        $this->inspectorSQLLogger->startQuery('COMMIT');

        try {
            parent::commit();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }

    public function rollBack(): void
    {
        $this->inspectorSQLLogger->startQuery('ROLLBACK');

        try {
            parent::rollBack();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }
}
