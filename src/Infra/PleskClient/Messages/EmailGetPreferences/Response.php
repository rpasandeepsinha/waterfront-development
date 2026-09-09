<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\EmailGetPreferences;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as ResponseStatus;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class Response extends BaseResponse
{
    private string $catchAllForward;

    private bool $spamProtectSign = false;

    private bool $mailservice = false;

    private string $webmail = '';

    private string $webmailCertificate = '';

    private int $errorCode;

    private string $errorText;

    public function getResult(): Result
    {
        $result = new Result();

        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        $result->setCatchAll($this->catchAllForward);
        $result->spamProtectSignEnabled = $this->spamProtectSign;
        $result->mailService = $this->mailservice;
        $result->webmailCertificate = $this->webmailCertificate;
        $result->webmail = $this->webmail;

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
            $result = $xmlResponse->mail->{'get_prefs'}->result;
        }

        $this->status = (string) $result->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }

        $this->catchAllForward = (string) $result->prefs->{'nonexistent-user'}->forward;
        $this->spamProtectSign = (string) $result->prefs->{'spam-protect-sign'} === 'true';
        $this->mailservice = (string) $result->prefs->{'mailservice'} === 'true';
        $this->webmailCertificate = (string) $result->prefs->{'webmail-certificate'};
        $this->webmail = (string) $result->prefs->webmail;
    }
}
