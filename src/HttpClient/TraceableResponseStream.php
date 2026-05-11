<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\HttpClient;

use SplObjectStorage;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Generator;
use Iterator;

class TraceableResponseStream implements ResponseStreamInterface
{
    private Iterator $iterator;

    public function __construct(
        ResponseStreamInterface $stream,
        SplObjectStorage $wrapMap
    ) {
        $this->iterator = (function () use ($stream, $wrapMap): Generator {
            foreach ($stream as $response => $chunk) {
                if (isset($wrapMap[$response])) {
                    yield $wrapMap[$response] => $chunk;
                } else {
                    yield $response => $chunk;
                }
            }
        })();
    }

    public function getIterator(): Iterator
    {
        return $this->iterator;
    }

    public function key(): ResponseInterface
    {
        return $this->iterator->key();
    }

    public function current(): ChunkInterface
    {
        return $this->iterator->current();
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }
}
