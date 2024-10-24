<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Inspector\Symfony\Bundle\Inspectable\Doctrine\InspectorSQLSegmentTracer;

/**
 * @internal
 */
class InspectorDriver extends AbstractDriverMiddleware
{
    /** @var InspectorSQLSegmentTracer */
    protected $inspectorSQLSegmentTracer;

    public function __construct(
        DriverInterface $driver,
        InspectorSQLSegmentTracer $inspectorSQLSegmentTracer
    ) {
        parent::__construct($driver);

        $this->inspectorSQLSegmentTracer = $inspectorSQLSegmentTracer;
    }

    public function connect(array $params): ConnectionInterface
    {
        $connection = parent::connect($params);

        // Detect if the version of Doctrine DBAL installed is 3 or 4
        // https://github.com/symfony/symfony/issues/47962
        if ('void' !== (string) (new \ReflectionMethod(DriverInterface\Connection::class, 'commit'))->getReturnType()) {
            // Doctrine DBAL 3
            return new DBAL3\Connection(
                $connection,
                $this->inspectorSQLSegmentTracer
            );
        }

        // Doctrine DBAL 4
        return new Connection(
            $connection,
            $this->inspectorSQLSegmentTracer
        );
    }
}
