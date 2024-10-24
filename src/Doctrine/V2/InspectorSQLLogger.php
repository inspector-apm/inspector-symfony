<?php

namespace Inspector\Symfony\Bundle\Doctrine\V2;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Types\Type;
use Inspector\Inspector;
use Inspector\Symfony\Bundle\Doctrine\Middleware\InspectorSQLSegmentTracer;

class InspectorSQLLogger implements SQLLogger
{
    /** @var InspectorSQLSegmentTracer */
    protected $inspectorSQLSegmentTracer;

    /**
     * InspectorSQLLogger constructor.
     *
     * @param Inspector $inspector
     * @param array $configuration
     * @param string $connectionName
     */
    public function __construct(Inspector $inspector, array $configuration, string $connectionName)
    {
        $this->inspectorSQLSegmentTracer = new InspectorSQLSegmentTracer(
            $inspector,
            $configuration,
            $connectionName
        );
    }

    /**
     * Logs a SQL statement.
     *
     * @param string $sql SQL statement
     * @param array<int, mixed>|array<string, mixed>|null $params Statement parameters
     * @param array<int, Type|int|string|null>|array<string, Type|int|string|null>|null $types Parameter types
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->inspectorSQLSegmentTracer->startQuery($sql, $params, $types);
    }

    /**
     * Marks the last started query segment as stopped.
     */
    public function stopQuery(): void
    {
        $this->inspectorSQLSegmentTracer->stopQuery();
    }
}
