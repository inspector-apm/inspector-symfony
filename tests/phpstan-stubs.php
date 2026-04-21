<?php

declare(strict_types=1);

/**
 * Stubs for classes removed in newer versions of dependencies.
 * Loaded by PHPStan via bootstrapFiles to allow static analysis
 * of backward-compatibility code paths.
 */

// Doctrine DBAL <4 (removed in DBAL 4)
namespace Doctrine\DBAL\Logging;

interface SQLLogger
{
    public function startQuery(string $sql, ?array $params = null, ?array $types = null): void;
    public function stopQuery(): void;
}

// Symfony Security <7 (removed in Symfony 7)
namespace Symfony\Component\Security\Core;

use Symfony\Component\Security\Core\User\UserInterface;

class Security
{
    public function getUser(): ?UserInterface
    {
        return null;
    }
}

// Symfony HttpKernel <5 (removed in Symfony 5)
namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;

class GetResponseForExceptionEvent extends KernelEvent
{
    public function getException(): \Throwable
    {
        return new \Exception();
    }
}
