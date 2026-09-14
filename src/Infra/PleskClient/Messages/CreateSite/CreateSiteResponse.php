<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CreateSite;

use Exception;
use SimpleXMLElement;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;
use Webmozart\Assert\Assert;

class CreateSiteResponse extends BaseResponse
{
    private ?int $errorCode = null;

    private string $errorText = '';

    private string $domainId = '';

    private string $domainGuid = '';

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getResult(): CreateSiteResult
    {
        $result = new CreateSiteResult();
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            $errorCode = $this->errorCode;
            Assert::notNull($errorCode);
            $result->setErrorCode($errorCode);
            $result->setErrorMessage($this->errorText);
        }

        $result->domainId = $this->domainId;
        $result->domainGuid = $this->domainGuid;

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
        $siteAdd = (array) $xmlResponse->site->add;

        if (! key_exists('result', $siteAdd)) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = 0;
            $this->errorText = 'No result element found in response';

            return;
        }

        $this->status = (string) $siteAdd['result']->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $siteAdd['result']->errcode;
            $this->errorText = (string) $siteAdd['result']->errtext;

            return;
        }

        $this->domainId = (string) $siteAdd['result']->id;
        $this->domainGuid = (string) $siteAdd['result']->guid;
    }
}
