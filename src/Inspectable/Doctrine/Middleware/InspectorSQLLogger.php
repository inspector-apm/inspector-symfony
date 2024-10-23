<?php

namespace Inspector\Symfony\Bundle\Inspectable\Doctrine\Middleware;

use Inspector\Inspector;

class InspectorSQLLogger
{
    /** @var Inspector */
    protected $inspector;

    /** @var \Inspector\Models\PerformanceModel|\Inspector\Models\Segment */
    protected $segment;

    /** @var array */
    protected $configuration;

    /** @var string */
    protected $connectionName;

    public function __construct(
        Inspector $inspector,
        array $configuration,
        string $connectionName
    ) {
        $this->inspector = $inspector;
        $this->configuration = $configuration;
        $this->connectionName = $connectionName;
    }

    /**
     * @return Inspector
     */
    public function getInspector(): Inspector
    {
        return $this->inspector;
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
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
        // This check is needed as transaction is flushed in MessengerEventSubscriber
        if (!$this->inspector->hasTransaction()) {
            return;
        }

        $this->segment = $this->inspector->startSegment("doctrine:".$this->connectionName, substr($sql, 0, 50));

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
        // This check is needed as transaction is flushed in MessengerEventSubscriber
        if (!$this->inspector->hasTransaction()) {
            return;
        }

        if (null === $this->segment) {
            throw new \LogicException('Attempt to stop a segment that has not been started');
        }

        $this->segment->end();
        $this->segment = null;
    }
}
