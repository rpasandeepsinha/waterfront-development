<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Services;

use Saloon\Exceptions\SaloonException;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Microsoft365\Interfaces\Microsoft365ProvisionServiceInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365AuthorizationUrlRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365TenantIdRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantAuthorizationUrlResult;
use Waterfront\Domain\Provision\Microsoft365\Results\TenantIdResult;
use Waterfront\Infra\MicrosoftOnlineClient\Exceptions\TenantNotFoundException;
use Waterfront\Infra\MicrosoftOnlineClient\MicrosoftOnlineClient;

class MicrosoftOnlineService implements Microsoft365ProvisionServiceInterface
{
    public function __construct(
        private readonly MicrosoftOnlineClient $microsoftOnlineClient,
    ) {
    }

    public function getTenantId(Microsoft365TenantIdRequest $tenantIdRequest): ProvisionResultInterface
    {
        $tenantName = $tenantIdRequest->tenantName;

        try {
            $tenantId = $this->microsoftOnlineClient->getTenantIdByTenantName($tenantName);

            if ($tenantId === null) {
                throw new TenantNotFoundException($tenantName);
            }

            return new TenantIdResult(
                provisionData: $tenantIdRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                tenantId: $tenantId,
            );
        } catch (TenantNotFoundException|SaloonException $e) {
            return new TenantIdResult(
                provisionData: $tenantIdRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $e,
            );
        }
    }

    public function getAuthorizationUrl(Microsoft365AuthorizationUrlRequest $tenantIdRequest): ProvisionResultInterface
    {
        $tenantName = $tenantIdRequest->tenantName;

        try {
            $tenantOpenId = $this->microsoftOnlineClient->getOpenIdConfiguration($tenantName);

            return new TenantAuthorizationUrlResult(
                provisionData: $tenantIdRequest,
                provisionStatus: ProvisionStatus::SUCCESS,
                authorizationUrl: $tenantOpenId->authorizationEndpoint,
            );
        } catch (TenantNotFoundException|SaloonException $e) {
            return new TenantAuthorizationUrlResult(
                provisionData: $tenantIdRequest,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $e,
            );
        }
    }
}
