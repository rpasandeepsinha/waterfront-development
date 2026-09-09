<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Webmozart\Assert\Assert;

class DomainTransferResponse
{
    private DomainStatus $status;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode;

    private string $reason;

    private ?string $expirationDate = null;

    private ?string $renewalDate = null;

    private ?string $transferSecret = null;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();
        $this->parseReply($response->getBody()->__toString());
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getResult(): TransferResult
    {
        $result = new TransferResult($this->status->value);

        if ($this->responseCode == 0) {
            if ($this->expirationDate !== null) {
                $result->setExpirationDate($this->expirationDate);
            }
            if ($this->renewalDate !== null) {
                $result->setRenewalDate($this->renewalDate);
            }
            if ($this->transferSecret !== null) {
                $result->setTransferSecret($this->transferSecret);
            }
        } else {
            $result->setReason($this->reason);
        }

        return $result;
    }

    private function collectTransferInfo(SimpleXMLElement $data): void
    {
        $status = DomainStatus::tryFrom((string) $data->status);
        Assert::notNull($status);

        $this->status = $status;

        if ((string) $data->expirationDate !== '') {
            $this->expirationDate = (string) $data->expirationDate;
        }
        if ((string) $data->renewalDate !== '') {
            $this->renewalDate = (string) $data->renewalDate;
        }
        if ((string) $data->authCode !== '') {
            $this->transferSecret = (string) $data->authCode;
        }
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
            $this->collectTransferInfo($xmlResponse->reply->data);
        } else {
            $reason = (string) $xmlResponse->reply->desc;

            if (property_exists($xmlResponse->reply, 'data')) {
                $reason .= "\n" . $xmlResponse->reply->data;
            }

            $this->status = DomainStatus::FAILED;
            $this->reason = $reason;
        }
    }
}
