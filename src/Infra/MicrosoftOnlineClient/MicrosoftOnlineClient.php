<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient;

use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Waterfront\Infra\MicrosoftOnlineClient\Connectors\MicrosoftOnlineConnector;
use Waterfront\Infra\MicrosoftOnlineClient\DTO\MicrosoftErrorResponse;
use Waterfront\Infra\MicrosoftOnlineClient\DTO\OpenIdConfiguration;
use Waterfront\Infra\MicrosoftOnlineClient\Exceptions\TenantNotFoundException;
use Waterfront\Infra\MicrosoftOnlineClient\Requests\GetOpenIdConfigurationRequest;
use Waterfront\Infra\MicrosoftOnlineClient\Serializers\MicrosoftOnlineSerializer;

class MicrosoftOnlineClient
{
    public function __construct(
        private readonly MicrosoftOnlineConnector $connector,
        private readonly MicrosoftOnlineSerializer $microsoftOnlineSerializer,
    ) {
    }

    /**
     * @throws ClientException
     * @throws TenantNotFoundException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getOpenIdConfiguration(string $tenantName): OpenIdConfiguration
    {
        $request = new GetOpenIdConfigurationRequest($tenantName);

        try {
            $response = $this->connector->send($request);
        } catch (ClientException $exception) {
            /** @var MicrosoftErrorResponse $errorResponse */
            $errorResponse = $this->microsoftOnlineSerializer->deserialize(
                $exception->getResponse()->body(),
                MicrosoftErrorResponse::class,
                'json',
            );

            if ($errorResponse->error === 'invalid_tenant') {
                throw new TenantNotFoundException($tenantName, $exception);
            }

            throw $exception;
        }

        /** @var OpenIdConfiguration $openIdConfiguration */
        $openIdConfiguration = $this->microsoftOnlineSerializer->deserialize(
            $response->body(),
            OpenIdConfiguration::class,
            'json',
        );

        return $openIdConfiguration;
    }

    /**
     * @throws ClientException
     * @throws TenantNotFoundException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getTenantIdByTenantName(string $tenantName): ?string
    {
        $openIdConfiguration = $this->getOpenIdConfiguration($tenantName);
        $matched = preg_match(
            '#login.microsoftonline.com\/([^\/]+)\/#',
            $openIdConfiguration->authorizationEndpoint,
            $matches,
        );

        if ($matched !== 1) {
            return null;
        }

        $tenantId = $matches[1];

        if (strlen($tenantId) === 0) {
            // Tenant does not exist
            return null;
        }

        return $tenantId;
    }
}
