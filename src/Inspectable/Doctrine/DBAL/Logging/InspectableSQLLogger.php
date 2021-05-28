<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\DBAL\Logging;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Types\Type;
use Inspector\Inspector;

class InspectableSQLLogger implements SQLLogger
{
    protected const SEGMENT_TYPE = 'SQL';

    /** @var Inspector */
    protected $inspector;

    /** @var \Inspector\Models\PerformanceModel|\Inspector\Models\Segment */
    protected $segment;

    /** @var array */
    protected $configuration;

    public function __construct(Inspector $inspector, array $configuration)
    {
        $this->inspector = $inspector;
        $this->configuration = $configuration;
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
        $this->segment = $this->inspector->startSegment(self::SEGMENT_TYPE, substr($sql, 0, 50));

        $context = ['sql' => $sql];

        // Checks if option is set and is convertible to true
        if (!empty($this->configuration['query_bindings'])) {
            $context['bindings'] = $params;
        }

        // TODO: connection name
        $this->segment->addContext('DB', $context);
    }

    /**
     * Marks the last started query segment as stopped.
     */
    public function stopQuery(): void
    {
        if (null === $this->segment) {
            throw new \LogicException('Attempt to stop a segment that has not been started');
        }

        $this->segment->end();
        $this->segment = null;
    }
}
