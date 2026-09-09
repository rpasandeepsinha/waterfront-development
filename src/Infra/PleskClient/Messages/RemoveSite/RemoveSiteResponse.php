<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\RemoveSite;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as ResponseStatus;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class RemoveSiteResponse extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getResult(): RemoveSiteResult
    {
        $result = new RemoveSiteResult();

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
        // If the HTTP response is not 200, we won't get a valid xml body to parse.
        if ($this->statusCode !== ResponseStatus::HTTP_OK) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = $this->statusCode;
            $this->errorText = $this->statusMessage;
            return;
        }

        $xmlResponse = new SimpleXMLElement($reply);
        $siteDelete = (array) $xmlResponse->site->del;

        if (! key_exists('result', $siteDelete)) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = 0;
            $this->errorText = 'No result element found in response';
            return;
        }

        $this->status = (string) $siteDelete['result']->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $siteDelete['result']->errcode;
            $this->errorText = (string) $siteDelete['result']->errtext;
        }
    }
}
