<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class DomainRetrieveResponse
{
    private bool $success = false;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode;

    private string $reason;

    /** @var array<mixed> */
    private $domain;

    private string $ownerHandle;

    private string $adminHandle;

    private string $techHandle;

    private string $billingHandle;

    private string $status;

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

    /**
     * @return array<mixed>
     */
    public function getDomain(): array
    {
        return $this->domain;
    }

    public function getOwnerHandle(): string
    {
        return $this->ownerHandle;
    }

    public function getAdminHandle(): string
    {
        return $this->adminHandle;
    }

    public function getTechHandle(): string
    {
        return $this->techHandle;
    }

    public function getBillingHandle(): string
    {
        return $this->billingHandle;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;
        $this->responseCode = $responseCode;

        if ($responseCode === 0) {
            $this->success = true;
            $this->domain = (array) $xmlResponse->reply->data->domain;
            $this->ownerHandle = (string) $xmlResponse->reply->data->ownerHandle;
            $this->adminHandle = (string) $xmlResponse->reply->data->adminHandle;
            $this->techHandle = (string) $xmlResponse->reply->data->techHandle;
            $this->billingHandle = (string) $xmlResponse->reply->data->billingHandle;
            $this->status = (string) $xmlResponse->reply->data->status;
        } else {
            $this->success = false;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
