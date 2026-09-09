<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CustomerCreate;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private ?int $errorCode = null;

    private string $errorText = '';

    private string $customerId;

    private string $customerGuid;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getCustomerGuid(): string
    {
        return $this->customerGuid;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);
        if ($this->status === self::STATUS_OK) {
            $result->setCustomerId($this->customerId);
            $result->setCustomerGuid($this->customerGuid);
        } else {
            /** @var int $errorCode */
            $errorCode = $this->errorCode;
            $result->setErrorCode($errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        // If the HTTP response is not 200, we won't get a valid xml body to parse.
        if ($this->statusCode !== 200) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->customer->add->result;
        }

        $this->status = (string) $result->status;
        if ($this->status === self::STATUS_OK) {
            $this->customerId = (string) $result->id;
            $this->customerGuid = (string) $result->guid;
        } else {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
