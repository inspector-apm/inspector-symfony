<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\HttpClient;

use Inspector\Inspector;
use SplObjectStorage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

use function parse_url;

class TraceableHttpClient implements HttpClientInterface, ResetInterface
{
    public function __construct(
        protected HttpClientInterface $client,
        protected Inspector $inspector
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->inspector->canAddSegments()) {
            return $this->client->request($method, $url, $options);
        }

        $segment = $this->inspector->startSegment('http.client', $method.' '.$url);

        $parsedUrl = parse_url($url);
        $segment->addContext('http', [
            'method' => $method,
            'url' => $url,
            'host' => ($parsedUrl['host'] ?? '') . (isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : ''),
        ]);

        $response = $this->client->request($method, $url, $options);

        return new TraceableResponse($response, $segment, $method, $url);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        $unwrap = [];
        $wrapMap = new SplObjectStorage();

        foreach ($responses as $response) {
            if ($response instanceof TraceableResponse) {
                $inner = $response->getInnerResponse();
                $unwrap[] = $inner;
                $wrapMap[$inner] = $response;
            } else {
                $unwrap[] = $response;
            }
        }

        $stream = $this->client->stream($unwrap, $timeout);

        if ($wrapMap->count() === 0) {
            return $stream;
        }

        return new TraceableResponseStream($stream, $wrapMap);
    }

    public function withOptions(array $options): static
    {
        /** @phpstan-ignore-next-line */
        return new static($this->client->withOptions($options), $this->inspector);
    }

    public function reset(): void
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }
    }
}
