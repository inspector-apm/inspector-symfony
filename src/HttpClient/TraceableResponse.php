<?php

namespace Inspector\Symfony\Bundle\HttpClient;

use Inspector\Models\Segment;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TraceableResponse implements ResponseInterface
{
    private ResponseInterface $response;
    private Segment $segment;
    private string $method;
    private string $url;
    private bool $segmentEnded = false;

    public function __construct(ResponseInterface $response, Segment $segment, string $method, string $url)
    {
        $this->response = $response;
        $this->segment = $segment;
        $this->method = $method;
        $this->url = $url;
    }

    public function getStatusCode(): int
    {
        $statusCode = $this->response->getStatusCode();

        $this->addContext([
            'status_code' => $statusCode,
        ]);

        if ($statusCode >= 400) {
            $this->segment->setColor('red');
        }

        $this->endSegment();

        return $statusCode;
    }

    public function getHeaders(bool $throw = true): array
    {
        $headers = $this->response->getHeaders($throw);

        $this->addContext([
            'response_headers' => $headers,
        ]);

        $this->endSegment();

        return $headers;
    }

    public function getContent(bool $throw = true): string
    {
        $content = $this->response->getContent($throw);

        $this->addContext([
            'response_body_size' => strlen($content),
        ]);

        $this->endSegment();

        return $content;
    }

    public function toArray(bool $throw = true): array
    {
        $content = $this->response->toArray($throw);

        $this->endSegment();

        return $content;
    }

    public function cancel(): void
    {
        $this->response->cancel();
        $this->endSegment();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    public function __destruct()
    {
        $this->endSegment();
    }

    public function getInnerResponse(): ResponseInterface
    {
        return $this->response;
    }

    private function addContext(array $data): void
    {
        if ($this->segmentEnded) {
            return;
        }

        $this->segment->addContext('http', $data);
    }

    private function endSegment(): void
    {
        if ($this->segmentEnded) {
            return;
        }

        $this->segmentEnded = true;
        $this->segment->end();
    }
}
