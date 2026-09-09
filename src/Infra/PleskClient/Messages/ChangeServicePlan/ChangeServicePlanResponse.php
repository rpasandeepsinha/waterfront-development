<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ChangeServicePlan;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class ChangeServicePlanResponse extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    private string $hostingId;

    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);
        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        return $result;
    }

    public function getHostingId(): string
    {
        return $this->hostingId;
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
            $result = $xmlResponse->webspace->{'switch-subscription'}->result;
        }

        $status = (string) $result->status;
        $this->status = $status;

        if ($status === self::STATUS_OK) {
            $this->hostingId = (string) $result->id;
        } else {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
