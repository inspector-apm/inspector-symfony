<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Doctrine\Middleware;

use Doctrine\DBAL\Types\Type;
use Inspector\Inspector;
use Inspector\Models\Segment;
use LogicException;

class InspectorSQLSegmentTracer
{
    protected ?Segment $segment = null;

    public function __construct(
        protected Inspector $inspector,
        protected array $configuration,
        protected string $connectionName
    ) {
    }

    /**
     * Trace an SQL statement.
     *
     * @param string $sql SQL statement
     * @param array<int, mixed>|array<string, mixed>|null $params Statement parameters
     * @param array<int, Type|int|string|null>|array<string, Type|int|string|null>|null $types Parameter types
     */
    public function startQuery(string $sql, ?array $params = null, ?array $types = null): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $this->segment = $this->inspector->startSegment("db.doctrine:{$this->connectionName}", $sql);

        $context = ['sql' => $sql];

        // Checks if option is set and is convertible to true
        if (!empty($this->configuration['query_bindings']) && $params) {
            $context['bindings'] = $params;
        }

        $this->segment->addContext('DB', $context);
    }

    /**
     * Marks the last started query segment as stopped.
     */
    public function stopQuery(): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        if (null === $this->segment) {
            throw new LogicException('Attempt to stop a segment that has not been started');
        }

        $this->segment->end();
        $this->segment = null;
    }
}
