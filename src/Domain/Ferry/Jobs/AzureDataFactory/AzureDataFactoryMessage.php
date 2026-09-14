<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\AzureDataFactory;

use Waterfront\Domain\Ferry\Enums\AzureDataFactoryMessageType;

class AzureDataFactoryMessage
{
    /**
     * @param array<int|string, mixed> $body
     */
    private function __construct(
        private readonly string $type,
        private readonly array $body = [],
    ) {
    }

    /**
     * @param mixed[] $body
     */
    public static function create(string $type, array $body = []): AzureDataFactoryMessage
    {
        return new self(AzureDataFactoryMessageType::from($type)->value, $body);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @return array{type: string, data:array<int|string, mixed>}
     */
    public function toArray(): array
    {
        return ['type' => $this->getType(), 'data' => $this->getBody()];
    }
}
