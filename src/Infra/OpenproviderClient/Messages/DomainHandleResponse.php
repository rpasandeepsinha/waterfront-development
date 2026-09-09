<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class DomainHandleResponse
{
    private bool $success = false;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode;

    private string $reason;

    private string $handleId;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();

        if ($this->getStatusCode() === 200) {
            $this->parseReply((string) $response->getBody());
        }
    }

    public function isSuccess(): bool
    {
        return $this->success;
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

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getResult(): string
    {
        return $this->handleId;
    }

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = $xmlResponse->reply->code;
        $this->responseCode = (int) $responseCode;

        if ($responseCode == 0) {
            $this->success = true;
            $this->handleId = (string) $xmlResponse->reply->data->handle;
        } else {
            $this->success = false;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
