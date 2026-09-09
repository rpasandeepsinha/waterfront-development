<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain;

use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Processes the response from spam experts.
 */
class Response
{
    /** @var string */
    public const STATUS_OK = 'SUCCESS';

    /** @var string */
    public const STATUS_ERROR = 'ERROR';

    /** @var string */
    private $status;

    /** @var string */
    private $statusCode;

    /** @var string */
    private $statusMessage;

    /** @var string */
    private $body;

    /**
     * Get the response parts.
     */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = strval($response->getStatusCode());
        $this->statusMessage = $response->getReasonPhrase();

        $this->body = $response->getBody()->getContents();

        $this->status = self::STATUS_ERROR;
        if (Str::startsWith($this->body, self::STATUS_OK)) {
            $this->status = self::STATUS_OK;
        }
    }

    /**
     * Find out if the domain was removed successfully.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Get the status code.
     */
    public function getStatusCode(): string
    {
        return $this->statusCode;
    }

    /**
     * Get the status message.
     */
    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    /**
     * Get the message in the response body.
     */
    public function getContent(): string
    {
        return $this->body;
    }
}
