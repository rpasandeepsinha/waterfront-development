<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Psr\Http\Message\ResponseInterface;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

abstract class BaseResponse
{
    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode = 0;

    private string $responseText = '';

    private bool $success = false;

    /** @var mixed[] */
    private array $attributes = [];

    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();

        if ($this->statusCode === 200) {
            $this->parse((string) $response->getBody());
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    public function getResponseText(): string
    {
        return $this->responseText;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return mixed[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    private function parse(string $body): void
    {
        $decoded = OpsXml::decode($body);

        $responseCode = $decoded['response_code'] ?? null;
        $responseText = $decoded['response_text'] ?? null;
        $attributes = $decoded['attributes'] ?? null;

        $this->responseCode = is_string($responseCode) ? (int) $responseCode : 0;
        $this->responseText = is_string($responseText) ? $responseText : '';
        $this->success = ($decoded['is_success'] ?? null) === '1';
        $this->attributes = is_array($attributes) ? $attributes : [];
    }
}
