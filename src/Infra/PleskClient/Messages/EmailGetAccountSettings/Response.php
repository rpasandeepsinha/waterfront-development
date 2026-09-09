<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailGetAccountSettings;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as ResponseStatus;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    /**
     * @var mixed[]
     */
    private array $responseData = [];

    public function getResult(): Result
    {
        $result = new Result();

        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        $result->setResponseBody($this->responseData);

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        // If the HTTP response is not 200, we won't get a valid xml body to parse.
        if ($this->statusCode !== ResponseStatus::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);
        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->mail->{'get_info'}->result;
        }

        $this->status = (string) $result->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }

        $encoded = json_encode($xmlResponse, JSON_THROW_ON_ERROR);

        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        $this->responseData = $decoded;
    }
}
