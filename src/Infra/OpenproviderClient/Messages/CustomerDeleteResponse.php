<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use Exception;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class CustomerDeleteResponse
{
    private const string STATUS_OK = 'ok';

    private const string STATUS_ERROR = 'error';

    private string $status;

    private string $reason;

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

    /**
     * @throws Exception
     */
    private function parseReply(string $reply): void
    {
        $xmlResponse = new SimpleXMLElement($reply);
        $responseCode = (int) $xmlResponse->reply->code;

        if ($responseCode == 0) {
            $this->status = self::STATUS_OK;
        } else {
            $this->status = self::STATUS_ERROR;
            $this->reason = (string) $xmlResponse->reply->desc;
        }
    }
}
