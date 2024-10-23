<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;

/**
 * @internal
 */
class Statement extends AbstractStatementMiddleware
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

    protected string $sql;

    protected array $params = [];

    public function __construct(
        StatementInterface $statement,
        InspectorSQLLogger $inspectorSQLLogger,
        string $sql
    ) {
        parent::__construct($statement);

        $this->inspectorSQLLogger = $inspectorSQLLogger;
        $this->sql = $sql;
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;

        parent::bindValue($param, $value, $type);
    }

    public function execute(): ResultInterface
    {
        $this->inspectorSQLLogger->startQuery($this->sql, $this->params);

        try {
            return parent::execute();
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }
}
