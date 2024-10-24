<?php

namespace Inspector\Symfony\Bundle\Doctrine\Middleware\V4;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Inspector\Symfony\Bundle\Doctrine\Middleware\InspectorSQLSegmentTracer;

/**
 * Statement class for Doctrine DBAL4+
 *
 * @internal
 */
class Statement extends AbstractStatementMiddleware
{
    /** @var InspectorSQLSegmentTracer */
    protected $inspectorSQLSegmentTracer;

    protected string $sql;

    protected array $params = [];

    public function __construct(
        StatementInterface $statement,
        InspectorSQLSegmentTracer $inspectorSQLSegmentTracer,
        string $sql
    ) {
        parent::__construct($statement);

        $this->inspectorSQLSegmentTracer = $inspectorSQLSegmentTracer;
        $this->sql                       = $sql;
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->params[$param] = $value;

        parent::bindValue($param, $value, $type);
    }

    public function execute(): ResultInterface
    {
        $this->inspectorSQLSegmentTracer->startQuery($this->sql, $this->params);

        try {
            return parent::execute();
        } finally {
            $this->inspectorSQLSegmentTracer->stopQuery();
        }
    }
}
