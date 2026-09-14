<?php

declare(strict_types=1);

namespace Waterfront\Infra\PleskClient\Messages;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

abstract class BaseResponse
{
    /** @var string */
    public const STATUS_OK = 'ok';

    /** @var string */
    public const STATUS_ERROR = 'error';

    protected string $status;

    protected int $statusCode;

    protected string $statusMessage;

    protected string $httpResponse;

    /**
     * @throws JsonException
     */
    public function __construct(ResponseInterface $response)
    {
        $this->httpResponse = $response->getBody()->__toString();
        $this->statusCode = $response->getStatusCode();
        $this->statusMessage = $response->getReasonPhrase();
        $this->parseReply((string) $response->getBody());

        if (boolval(Config::get('hosting-service-client.connection.debug_mode'))) {
            Log::info('===== Start plesk response debug =====');

            $json = json_encode([
                'httpResponse' => $response->getBody()->__toString(),
                'statusCode' => $response->getStatusCode(),
                'statusMessage' => $response->getReasonPhrase(),
                'reply' => (string) $response->getBody(),
            ], JSON_THROW_ON_ERROR);

            Log::info($json);
            Log::info('===== END plesk response debug =====');
        }
    }

    /**
     * Get the status code returned by Plesk.
     */
    final public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    final public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    final public function getStatus(): string
    {
        return $this->status;
    }

    /**
     *
     * @throws Exception
     *
     * @return array<mixed>
     */
    final protected function xmlToArray(string $xml): array
    {
        $nodes = json_encode(new SimpleXMLElement($xml), JSON_THROW_ON_ERROR);
        $decoded = json_decode($nodes, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($decoded));

        return $decoded;
    }

    /**
     * @param array<mixed>  $nodes
     * @param array<string> $nodeNames
     *
     * @return array<mixed>
     */
    final protected function getNodeArray(array $nodes, array $nodeNames = []): array
    {
        $current = $nodes;
        $match = false;
        foreach ($nodeNames as $iValue) {
            if (array_key_exists($iValue, $current) && is_array($current[$iValue])) {
                $current = $current[$iValue];
                $match = true;
            } else {
                $match = false;
            }
        }

        return $match ? $current : [];
    }

    final protected function setStatus(string $status): void
    {
        $this->status = $status;
    }

    abstract protected function parseReply(string $reply): void;
}
