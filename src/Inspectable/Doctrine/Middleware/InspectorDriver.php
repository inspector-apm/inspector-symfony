<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * @internal
 */
class InspectorDriver extends AbstractDriverMiddleware
{
    /** @var InspectorSQLLogger */
    protected $inspectorSQLLogger;

    public function __construct(
        DriverInterface $driver,
        InspectorSQLLogger $inspectorSQLLogger
    ) {
        parent::__construct($driver);

        $this->inspectorSQLLogger = $inspectorSQLLogger;
    }

    public function connect(array $params): ConnectionInterface
    {
        $connection = parent::connect($params);

        if ('void' !== (string) (new \ReflectionMethod(DriverInterface\Connection::class, 'commit'))->getReturnType()) {
            return new DBAL3\Connection(
                $connection,
                $this->inspectorSQLLogger
            );
        }

        return new Connection(
            $connection,
            $this->inspectorSQLLogger
        );
    }
}
