<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Clients;

use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantUsages;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\Tenant\CreateTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\DeleteTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantPricingSettingsRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\GetTenantUsagesRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\PutTenantPricingSettingsRequest;
use Waterfront\Infra\AcronisClient\Requests\Tenant\PutTenantRequest;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;

class AcronisTenantClient
{
    public function __construct(
        private readonly AcronisConnector $connector,
    ) {
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     */
    public function get(string $tenantId, ?bool $embedPath = null, ?bool $allowDeleted = null): Tenant
    {
        $response = $this->connector->send(new GetTenantRequest(
            tenantId: $tenantId,
            embedPath: $embedPath,
            allowDeleted: $allowDeleted,
        ));

        try {
            $tenant = AcronisSerializer::get()->deserialize($response->body(), Tenant::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(Tenant::class, $response->body(), $exception);
        }

        return $tenant;
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function create(Tenant $payload): Tenant
    {
        $response = $this->connector->send(new CreateTenantRequest(
            payload: $payload,
        ));

        try {
            $tenant = AcronisSerializer::get()->deserialize($response->body(), Tenant::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(Tenant::class, $response->body(), $exception);
        }

        return $tenant;
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function update(string $tenantId, Tenant $payload): Tenant
    {
        $response = $this->connector->send(new PutTenantRequest(
            tenantId: $tenantId,
            payload: $payload,
        ));

        try {
            $tenant = AcronisSerializer::get()->deserialize($response->body(), Tenant::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(Tenant::class, $response->body(), $exception);
        }

        return $tenant;
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     */
    public function getPricingSettings(string $tenantId): TenantPricingSettings
    {
        $response = $this->connector->send(
            new GetTenantPricingSettingsRequest($tenantId)
        );

        try {
            $tenantPricingSettings = AcronisSerializer::get()->deserialize($response->body(), TenantPricingSettings::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(TenantPricingSettings::class, $response->body(), $exception);
        }

        return $tenantPricingSettings;
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function updatePricingSettings(string $tenantId, TenantPricingSettings $payload): TenantPricingSettings
    {
        $response = $this->connector->send(new PutTenantPricingSettingsRequest(
            tenantId: $tenantId,
            payload: $payload
        ));

        try {
            $tenantPricingSettings = AcronisSerializer::get()->deserialize($response->body(), TenantPricingSettings::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(TenantPricingSettings::class, $response->body(), $exception);
        }

        return $tenantPricingSettings;
    }

    /**
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function delete(string $tenantId, int $version): void
    {
        $this->connector->send(new DeleteTenantRequest(
            tenantId: $tenantId,
            version: $version,
        ));
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function getTenantUsages(string $tenantId, ?string $usageNames = null, ?string $editions = null): TenantUsages
    {
        $response = $this->connector->send(new GetTenantUsagesRequest(
            tenantId: $tenantId,
            usageNames: $usageNames,
            editions: $editions,
        ));

        try {
            $tenantUsages = AcronisSerializer::get()->deserialize($response->body(), TenantUsages::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(TenantUsages::class, $response->body(), $exception);
        }

        return $tenantUsages;
    }
}
