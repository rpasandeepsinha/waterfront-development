<?php

declare(strict_types=1);

namespace Waterfront\Infra\WpToolkitClient;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Waterfront\Infra\WpToolkitClient\DTO\ConnectionDetails;
use Webmozart\Assert\Assert;

class Client
{
    private const string API_BASE_URL = '/api/modules/wp-toolkit';

    private readonly string $baseUrl;

    public function __construct(
        private readonly GuzzleClient $guzzleClient,
        private readonly ConnectionDetails $connectionDetails
    ) {
        $this->baseUrl = sprintf(
            '%s:%d%s',
            $this->connectionDetails->pleskHost,
            $this->connectionDetails->pleskPort,
            self::API_BASE_URL
        );

        $this->validateAuthenticationFields();
    }

    public function getExistingInstallations(): string
    {
        $requestUri = sprintf(
            '%s/v1/installations',
            $this->baseUrl,
        );

        return $this->guzzleClient->request(
            'GET',
            $requestUri,
            options: [
                RequestOptions::HEADERS => $this->getAuthenticationHeader(),
                RequestOptions::VERIFY => true,
            ]
        )
            ->getBody()
            ->getContents();
    }

    public function getCredentials(int $installationId): string
    {
        $requestUri = sprintf(
            '%s/v1/installations/%d/credentials',
            $this->baseUrl,
            $installationId
        );

        return $this->guzzleClient->request(
            'GET',
            $requestUri,
            options: [
                RequestOptions::HEADERS => $this->getAuthenticationHeader(),
                RequestOptions::VERIFY => true,
            ]
        )
            ->getBody()
            ->getContents();
    }

    /**
     * @return array|string[]
     */
    private function getAuthenticationHeader(): array
    {
        return $this->connectionDetails->token !== null
            ? ['X-API-Key' => $this->connectionDetails->token]
            : ['Authorization' => 'Basic ' . $this->getBasicAuthBase64String()];
    }

    private function getBasicAuthBase64String(): string
    {
        return base64_encode(
            sprintf(
                '%s:%s',
                $this->connectionDetails->username,
                $this->connectionDetails->password
            )
        );
    }

    private function validateAuthenticationFields(): void
    {
        if ($this->connectionDetails->token === null) {
            Assert::stringNotEmpty($this->connectionDetails->username, 'Username is required and cannot be empty when token is not set');
            Assert::stringNotEmpty($this->connectionDetails->password, 'Password is required and cannot be empty when token is not set');
        } else {
            Assert::stringNotEmpty($this->connectionDetails->token, 'The token cannot be empty when username and password are not set');
        }
    }
}
