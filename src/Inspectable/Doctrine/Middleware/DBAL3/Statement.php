<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware\DBAL3;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware\InspectorSQLLogger;

/**
 * @internal
 */
class Statement extends AbstractStatementMiddleware
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

    protected string $sql;

    public function __construct(
        StatementInterface $statement,
        InspectorSQLLogger $inspectorSQLLogger,
        string $sql
    ) {
        parent::__construct($statement);

        $this->inspectorSQLLogger = $inspectorSQLLogger;
        $this->sql = $sql;
    }

    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        return parent::bindParam($param, $variable, $type, ...\array_slice(\func_get_args(), 3));
    }

    public function bindValue($param, $value, $type = ParameterType::STRING): bool
    {
        return parent::bindValue($param, $value, $type);
    }

    public function execute($params = null): ResultInterface
    {
        $this->inspectorSQLLogger->startQuery($this->sql, $params);

        try {
            return parent::execute($params);
        } finally {
            $this->inspectorSQLLogger->stopQuery();
        }
    }
}
