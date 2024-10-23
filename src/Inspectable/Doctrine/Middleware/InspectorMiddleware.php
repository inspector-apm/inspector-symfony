<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Inspector\Inspector;

class InspectorMiddleware implements MiddlewareInterface
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

    /**
     * InspectorMiddleware constructor.
     *
     * @param Inspector $inspector
     * @param array     $configuration
     * @param string    $connectionName
     */
    public function __construct(
        Inspector $inspector,
        array $configuration,
        string $connectionName
    ) {
        $this->inspectorSQLLogger = new InspectorSQLLogger(
            $inspector,
            $configuration,
            $connectionName
        );
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new InspectorDriver(
            $driver,
            $this->inspectorSQLLogger
        );
    }
}
