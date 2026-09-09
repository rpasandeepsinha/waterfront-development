<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CertificateInstall;

use Exception;
use SimpleXMLElement;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private string $errorCode = '';

    private string $errorText = '';

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getResult(): string
    {
        return $this->status;
    }

    /**
     * @throws Exception
     */
    protected function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->attributes('system') === null) {
            $result = $xmlResponse->certificate->install->result;
        }

        $this->status = (string) $result->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (string) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
