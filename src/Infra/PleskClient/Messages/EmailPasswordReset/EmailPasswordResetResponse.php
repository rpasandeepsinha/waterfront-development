<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailPasswordReset;

use Exception;
use SimpleXMLElement;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class EmailPasswordResetResponse extends BaseResponse
{
    public string $mailName;

    private int $errorCode;

    private string $errorText;

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
        if ($result->attributes('system') === null) {
            $result = $xmlResponse->mail->update->set->result;
        }

        $status = (string) $result->status;
        $this->status = $status;

        if ($status === self::STATUS_OK) {
            $this->mailName = (string) $result->mailname->name;
        } else {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
