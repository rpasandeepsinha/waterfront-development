<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\SyncSubscription;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class SyncSubscriptionResponse extends BaseResponse
{
    private int $errorCode = 0;

    private string $errorText = '';

    public function getResult(): Result
    {
        $result = new Result();
        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        // Handle non-200 HTTP responses
        if ($this->statusCode !== HttpResponse::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->webspace->{'sync-subscription'}->result;
        }

        $this->status = (string) $result->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
