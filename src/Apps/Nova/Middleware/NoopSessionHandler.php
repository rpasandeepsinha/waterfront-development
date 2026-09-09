<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Middleware;

use SessionHandlerInterface;

class NoopSessionHandler implements SessionHandlerInterface
{
    public function close(): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 1;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        return false;
    }

    public function write(string $id, string $data): bool
    {
        return false;
    }
}
