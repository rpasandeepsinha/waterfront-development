<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ResellerHostingSetSpecs;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateResellerHosting\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    protected string $status;

    private int $errorCode;

    private string $errorText;

    private int $resellerId;

    public function getResellerId(): int
    {
        return $this->resellerId;
    }

    public function setResellerId(int $resellerId): void
    {
        $this->resellerId = $resellerId;
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    /**
     * @throws Exception
     */
    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);
        if ($this->status === self::STATUS_OK) {
            $result->setResellerId($this->resellerId);
        } else {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    /**
     *
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
        if ($result->attributes('system') === null) {
            $result = $xmlResponse->reseller->set->result;
        }

        $this->status = (string) $result->status;
        if ($this->status === self::STATUS_OK) {
            $this->resellerId = (int) $result->id;
        } else {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
