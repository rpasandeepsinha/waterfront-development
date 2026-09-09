<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\DnsDisableZone;

use Exception;
use Symfony\Component\HttpFoundation\Response as ResponseStatus;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class DnsDisableZoneResponse extends BaseResponse
{
    public int $errorCode;

    public string $errorText;

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
        if ($this->statusCode !== ResponseStatus::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;

            return;
        }

        $this->status = self::STATUS_OK;
        $this->errorCode = 0;
        $this->errorText = '';
    }
}
