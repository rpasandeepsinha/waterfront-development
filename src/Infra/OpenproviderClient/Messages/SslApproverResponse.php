<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Waterfront\Domain\Ssl\Interfaces\Models\Approver\Result;

class SslApproverResponse
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_ERROR = 'error';

    private string $status;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private string $reason;

    /** @var array<mixed> */
    private array $emails;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();
        $this->parseReply((string) $response->getBody());
    }

    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);
        if ($this->status === self::STATUS_OK) {
            $result->setEmails($this->emails);
        } else {
            $result->setErrorCode($this->statusCode);
            $result->setErrorMessage($this->statusMessage);
            $result->setReason($this->reason);
        }

        return $result;
    }

    public function isSuccess(): bool
    {
        return $this->status == self::STATUS_OK;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;
        if ($responseCode == 0) {
            $this->status = self::STATUS_OK;
            $this->emails = (array) $xmlResponse->reply->data->array->item;
        } else {
            $this->status = self::STATUS_ERROR;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
