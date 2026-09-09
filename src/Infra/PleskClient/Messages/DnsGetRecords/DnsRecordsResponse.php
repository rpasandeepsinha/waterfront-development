<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages\DnsGetRecords;

use Exception;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response as ResponseStatus;
use Waterfront\Infra\PleskClient\DTO\DnsRecord;
use Waterfront\Infra\PleskClient\Messages\BaseResponse;

class DnsRecordsResponse extends BaseResponse
{
    private int $errorCode;

    private string $errorText;

    /**
     * @var DnsRecord[]
     */
    private array $records;

    public function getResult(): DnsRecordsResult
    {
        $result = new DnsRecordsResult();

        $result->setResponseResult($this->httpResponse);
        $result->setStatus($this->status);

        if ($this->status !== self::STATUS_OK) {
            $result->setErrorCode($this->errorCode);
            $result->setErrorMessage($this->errorText);
        }

        $result->records = $this->records;

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
        $result = $xmlResponse->dns;
        $recordElement = (array) $result->get_rec;
        $records = (array) $recordElement['result'];

        if (count($records) === 0) {
            // No DNS records is a valid response
            $this->status = self::STATUS_OK;
            $this->errorCode = 0;
            $this->errorText = '';
            return;
        }

        // Plesk sends the response in each record, but we only need it once.
        $this->status = (string) array_first($records)->status;
        if ($this->status !== self::STATUS_OK) {
            $this->errorCode = (int) $result->errcode;
            $this->errorText = (string) $result->errtext;
        }

        foreach ($records as $dnsRecord) {
            $this->records[] = new DnsRecord(
                siteId: (int) $dnsRecord->data->{'site-id'},
                type: (string) $dnsRecord->data->type,
                host: (string) $dnsRecord->data->host,
                value: (string) $dnsRecord->data->value,
                opt: (string) $dnsRecord->data->opt !== '' ? (int) $dnsRecord->data->opt : null,
            );
        }
    }
}
