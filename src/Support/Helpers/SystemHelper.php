<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

class SystemHelper
{
    public function __construct(private readonly ?Request $globalRequest)
    {
    }

    public function getClientIp(): string
    {
        return $this->globalRequest?->getClientIp() ?? '127.0.0.1';
    }

    public function isRunningInConsole(): bool
    {
        return Application::getInstance()->runningInConsole();
    }
}
