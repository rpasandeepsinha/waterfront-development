<?php

declare(strict_types=1);

namespace Waterfront\Infra\PowerDnsClient\Entities;

class PowerDnsRecord
{
    public string $content;

    public bool $disabled = false;

    /**
     * @return array{content: string, disabled: bool}
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'disabled' => $this->disabled,
        ];
    }
}
