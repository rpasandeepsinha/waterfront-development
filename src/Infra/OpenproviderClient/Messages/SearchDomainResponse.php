<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use SimpleXMLElement;

class SearchDomainResponse
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_ERROR = 'error';

    private string $status;

    private string $reason;

    private readonly int $statusCode;

    private readonly string $statusMessage;

    private int $responseCode;

    /**
     * @var array<string, mixed>
     */
    private array $result;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();
        $this->parseReply($response->getBody());
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }

    /**
     * @return array<int, string>
     */
    public function getDomains(): array
    {
        $domains = [];
        $items = $this->getResult()['item'];
        assert(is_iterable($items));

        foreach ($items as $item) {
            assert(is_object($item));
            assert(property_exists($item, 'domain') && is_object($item->domain));
            assert(property_exists($item->domain, 'name') && property_exists($item->domain, 'extension'));
            $domains[] = $item->domain->name . '.' . $item->domain->extension;
        }

        return $domains;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @throws Exception
     */
    private function parseReply(StreamInterface $reply): void
    {
        $xmlResponse = new SimpleXMLElement((string) $reply);
        $this->responseCode = (int) $xmlResponse->reply->code;
        if ($this->responseCode === 0) {
            $this->status = self::STATUS_OK;
            $this->result = (array) $xmlResponse->reply->data->results->array;
        } else {
            $this->status = self::STATUS_ERROR;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
