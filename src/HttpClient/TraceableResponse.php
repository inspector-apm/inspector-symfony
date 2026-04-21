<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\HttpClient;

use Inspector\Models\Segment;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function strlen;

class TraceableResponse implements ResponseInterface
{
    protected bool $segmentEnded = false;

    /** @var array<string, mixed> */
    private array $context = [];

    public function __construct(
        protected ResponseInterface $response,
        protected Segment $segment,
        protected string $method,
        protected string $url
    ) {
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

        $this->context = array_merge($this->context, $data);
    }

    private function endSegment(): void
    {
        if ($this->segmentEnded) {
            return;
        }

        $this->segmentEnded = true;

        if (!empty($this->context)) {
            $this->segment->addContext('http', $this->context);
        }

        $this->segment->end();
    }
}
