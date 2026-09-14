<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;

class SslRetrieveResponse
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_ERROR = 'error';

    private string $status;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    /** @var array<mixed> */
    private array $responseData;

    private string $reason;

    private int $certificateId;

    private string $certificateStatus;

    private ?string $dnsRecord = null;

    private ?string $dnsValue = null;

    private string $csr;

    /**
     * @throws JsonException
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
        $result->setResponseData($this->responseData);

        if ($this->status === self::STATUS_OK) {
            $result->setCertificateId($this->certificateId);
            $result->setCertificateStatus($this->certificateStatus);
            $result->setIsCertificateActive($this->certificateStatus === DomainStatus::ACTIVE->value);
            $result->setCsr($this->csr);

            if ($this->dnsRecord !== null && $this->dnsValue !== null) {
                $result->setDnsRecord($this->dnsRecord);
                $result->setDnsValue($this->dnsValue);
            }
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
     * @throws JsonException
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;

        $decoded = json_decode(
            json_encode($xmlResponse->reply->data, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        assert(is_array($decoded));

        $this->responseData = $decoded;

        if ($responseCode == 0) {
            $this->status = self::STATUS_OK;
            $this->certificateId = (int) $xmlResponse->reply->data->id;
            $this->certificateStatus = (string) $xmlResponse->reply->data->status;
            $this->csr = (string) $xmlResponse->reply->data->csr;

            $this->dnsRecord = (string) $xmlResponse->reply->data->additionalData->array->item->dnsRecord;
            $this->dnsValue = (string) $xmlResponse->reply->data->additionalData->array->item->dnsValue;
        } else {
            $this->status = self::STATUS_ERROR;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
