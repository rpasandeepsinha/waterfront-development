<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList;

use Exception;
use SimpleXMLElement;
use Waterfront\Infra\PleskClient\DTO\Domain;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class CustomerGetDomainListResponse extends BaseResponse
{
    /**
     * @var Domain[]
     */
    public array $domains = [];

    private ?int $errorCode = null;

    private string $errorText = '';

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function getErrorText(): string
    {
        return $this->errorText;
    }

    public function getResult(): CustomerGetDomainListResult
    {
        $result = new CustomerGetDomainListResult();
        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            /** @var int $errorCode */
            $errorCode = $this->errorCode;
            $result->setErrorCode($errorCode);
            $result->setErrorMessage($this->errorText);
        }

        $result->domains = $this->domains;

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
        $domainList = (array) $xmlResponse->customer->{'get-domain-list'};

        if (! key_exists('result', $domainList)) {
            $this->status = self::STATUS_ERROR;
            $this->errorCode = 0;
            $this->errorText = 'No result element found in response';

            return;
        }

        $this->status = (string) $domainList['result']->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $domainList['result']->errcode;
            $this->errorText = (string) $domainList['result']->errtext;

            return;
        }

        $domainsResult = $domainList['result']->domains;

        foreach ($domainsResult->domain as $domain) {
            $this->domains[] = new Domain(
                id: (int) $domain->id,
                name: (string) $domain->name,
                asciiName: (string) $domain->{'ascii-name'},
                type: (string) $domain->type,
                isMain: (bool) $domain->{'is-main'},
                guid: (string) $domain->guid,
                externalId: (string) $domain->{'external-id'} === '' ? null : (int) $domain->{'external-id'},
                parentId: (string) $domain->{'parent-id'} === '' ? null : (int) $domain->{'parent-id'},
                domainId: (string) $domain->{'domain-id'} === '' ? null : (int) $domain->{'domain-id'},
            );
        }
    }
}
