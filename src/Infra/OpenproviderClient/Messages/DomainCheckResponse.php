<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;
use Waterfront\Domain\Domains\DTO\CheckResult;

class DomainCheckResponse
{
    private readonly int $statusCode;

    private readonly string $statusMessage;

    private string $status = CheckResult::STATUS_INVALID;

    private string $reason;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response, private string $domain)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();
        $this->parseReply((string) $response->getBody());
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getResult(): CheckResult
    {
        return new CheckResult($this->domain, $this->status, $this->reason);
    }

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);

        $replyCode = $xmlResponse->reply->code;

        // @todo: refactor to include all critical error codes
        //        we will pick this up when we deal with TLD validation
        // @see https://support.openprovider.eu/hc/en-us/articles/216644928-API-Error-Codes
        if ($replyCode == 196) {
            $errorMessage = $xmlResponse->reply->desc;

            throw new RuntimeException('open provider check domain failed - ' . $errorMessage, 1);
        }

        $item = $xmlResponse->reply->data->array->item;

        if (property_exists($item, 'domain')) {
            $this->domain = (string) $item->domain;
        }

        if (property_exists($item, 'status')) {
            $this->status = (string) $item->status;
        }

        if (property_exists($item, 'reason')) {
            $this->reason = (string) $item->reason;
        }
    }
}
