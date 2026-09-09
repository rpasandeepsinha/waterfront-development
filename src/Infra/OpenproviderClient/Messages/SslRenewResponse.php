<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class SslRenewResponse
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_ERROR = 'error';

    private string $status;

    private string $reason;

    private ?int $certificateId = null;

    /**
     * @throws Exception
     */
    public function __construct(ResponseInterface $response)
    {
        $this->parseReply((string) $response->getBody());
    }

    public function isSuccess(): bool
    {
        return $this->status == self::STATUS_OK;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCertificateId(): ?int
    {
        return $this->certificateId;
    }

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;
        if ($responseCode == 0) {
            $this->status = self::STATUS_OK;
            $this->certificateId = (int) $xmlResponse->reply->data->id;
        } else {
            $this->status = self::STATUS_ERROR;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
