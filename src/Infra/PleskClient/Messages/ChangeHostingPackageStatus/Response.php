<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\ChangeHostingPackageStatus;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    private ?string $responseResult = null;

    public function getResult(): Result
    {
        $result = new Result();
        $result->setStatus($this->status);

        if ($this->responseResult !== null) {
            $result->setResponseResult($this->responseResult);
        }

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
        if ($this->statusCode !== HttpResponse::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);
        $this->responseResult = (string) $xmlResponse->asXML();
        $result = $xmlResponse->system;
        if ($result->attributes('system') === null) {
            $result = $xmlResponse->site->set->result;
        }

        $status = (string) $result->status;
        $this->status = $status;

        if ($status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
