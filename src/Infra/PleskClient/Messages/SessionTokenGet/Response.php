<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\SessionTokenGet;

use Exception;
use SimpleXMLElement;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    private string $token = '';

    public function getToken(): string
    {
        return $this->token;
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
    protected function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);

        $result = $xmlResponse->system;
        if ($result->count() === 0) {
            $result = $xmlResponse->server->create_session->result;
        }

        $this->status = (string) $result->status;

        if ($this->status === self::STATUS_OK) {
            $this->token = (string) $result->id;
        } else {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }
    }
}
