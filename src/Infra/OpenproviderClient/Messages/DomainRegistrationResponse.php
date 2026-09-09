<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Waterfront\Domain\Domains\DTO\RegistrationResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Webmozart\Assert\Assert;

class DomainRegistrationResponse
{
    private DomainStatus $status;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode;

    private string $reason;

    private string $activationDate;

    private string $expirationDate;

    private string $expirationDateOpenprovider;

    private string $authCode;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
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

    public function getResult(): RegistrationResult
    {
        $result = new RegistrationResult($this->status);

        if ($this->responseCode === 0) {
            $result->setActivationDate($this->activationDate);
            $result->setExpirationDate($this->expirationDate);
            $result->setOpenProviderExpirationDate($this->expirationDateOpenprovider);
            $result->setAuthCode($this->authCode);
        } else {
            $result->setReason($this->reason);
        }

        return $result;
    }

    private function collectRegistrationInfo(SimpleXMLElement $data): void
    {
        $status = DomainStatus::tryFrom((string) $data->status);
        Assert::notNull($status);

        $this->status = $status;
        $this->activationDate = (string) $data->activationDate;
        $this->expirationDate = (string) $data->expirationDate;
        $this->expirationDateOpenprovider = (string) $data->expirationDateOpenprovider;
        $this->authCode = (string) $data->authCode;
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
            $this->collectRegistrationInfo($xmlResponse->reply->data);
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
