<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Waterfront\Infra\OpenSrsClient\Interfaces\OpenSrsConnectionInterface;
use Waterfront\Infra\OpenSrsClient\Support\OpsXml;

abstract class BaseRequest
{
    /** Seconds to wait for the TCP/TLS connection to horizon/rr-n1-tor on port 55443. */
    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** Seconds to wait for the full registry round trip before failing the request. */
    private const TIMEOUT_SECONDS = 30;

    protected string $apiUrl;

    protected string $username;

    protected string $apiKey;

    public function __construct(protected Client $client, OpenSrsConnectionInterface $connection)
    {
        $this->apiUrl = $connection->getApiUrl();
        $this->username = $connection->getUsername();
        $this->apiKey = $connection->getApiKey();
    }

    /**
     * @throws GuzzleException
     */
    public function send(): ResponseInterface
    {
        $xml = $this->getXml();

        return $this->client->request(
            'POST',
            $this->apiUrl,
            [
                'body'            => $xml,
                'headers'         => [
                    'Content-Type' => 'text/xml',
                    'X-Username'   => $this->username,
                    'X-Signature'  => $this->signature($xml),
                ],
                'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                'timeout'         => self::TIMEOUT_SECONDS,
                'allow_redirects' => false,
                'verify'          => true,
                'http_errors'     => false,
            ]
        );
    }

    public function getXml(): string
    {
        return OpsXml::encode($this->getMessage());
    }

    /**
     * OpenSRS authenticates with a double MD5 hash of the request body and the API key.
     *
     * @see https://domains.opensrs.guide/docs/quickstart
     */
    public function signature(string $xml): string
    {
        return md5(md5($xml . $this->apiKey) . $this->apiKey);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function getAttributes(): array;

    abstract protected function getObject(): string;

    abstract protected function getAction(): string;

    /**
     * Fields that sit next to protocol/object/action rather than inside `attributes`
     * (the `get` and `modify` commands place `domain` at this level).
     *
     * @return array<string, mixed>
     */
    protected function getTopLevelFields(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMessage(): array
    {
        return [
            'protocol'   => 'XCP',
            'object'     => $this->getObject(),
            'action'     => $this->getAction(),
            ...$this->getTopLevelFields(),
            'attributes' => $this->getAttributes(),
        ];
    }
}
