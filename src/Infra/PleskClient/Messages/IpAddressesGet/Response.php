<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\IpAddressesGet;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private ?int $errorCode = null;

    private string $errorText = '';

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
        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);
        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode ?? 0);
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
        if ($this->statusCode !== HttpResponse::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;
            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->ip->get->result;
        }

        $this->status = (string) $result->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
