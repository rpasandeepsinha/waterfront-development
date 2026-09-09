<?php

declare(strict_types=1);

namespace Waterfront\Infra\GandiClient\DTO;

class MessageResponse
{
    public function __construct(public string $message)
    {
    }
}
