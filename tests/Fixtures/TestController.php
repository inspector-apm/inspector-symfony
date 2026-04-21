<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Tests\Fixtures;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TestController
{
    public function index(): Response
    {
        return new Response('OK');
    }

    public function error(): void
    {
        throw new RuntimeException('Test exception');
    }
}
