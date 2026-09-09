<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages\Sso;

use Psr\Http\Message\ResponseInterface;

class Response
{
    /** @var string */
    public const STATUS_OK = 'SUCCESS';

    /** @var string */
    public const STATUS_ERROR = 'ERROR';

    private string $status;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private readonly string $body;

    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();

        $this->body = $response->getBody()->getContents();

        $this->status = self::STATUS_ERROR;

        if ($this->getStatusCode() === 200) {
            $this->status = self::STATUS_OK;
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getContent(): string
    {
        return $this->body;
    }
}
