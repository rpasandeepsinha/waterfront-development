<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenproviderClient\Messages;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Spatie\ArrayToXml\ArrayToXml;
use Waterfront\Infra\OpenproviderClient\Interfaces\OpenProviderConnectionInterface;

abstract class BaseRequest
{
    /** @var string */
    protected $apiUrl;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    public function __construct(protected Client $client, OpenProviderConnectionInterface $connection)
    {
        $this->apiUrl = $connection->getApiUrl();
        $this->username = $connection->getUsername();
        $this->password = $connection->getPassword();
    }

    /**
     * @throws GuzzleException
     */
    public function send(): ResponseInterface
    {
        $xmlMessage = $this->getXml();

        return $this->client->request(
            'POST',
            $this->apiUrl,
            [
                'body'        => $xmlMessage,
                'http_errors' => false,
            ]
        );
    }

    public function getXml(): string
    {
        return ArrayToXml::convert($this->getMessage(), 'openXML', false, 'UTF-8', options: ['convertNullToXsiNil' => true]);
    }

    /**
     * @return array<mixed>
     */
    protected function getMessage(): array
    {
        return [
            'credentials' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
        ];
    }
}
