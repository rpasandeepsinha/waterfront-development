<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Policies;

use Illuminate\Auth\Access\AuthorizationException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;

class HostingDeploymentPolicy
{
    public function __construct(
        private readonly ProductSpecRepository $productSpecRepository,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAvailableActions(HostingDeployment $deployment): array
    {
        $actions = [];

        try {
            self::assertCanSeeSpamFilterSso($deployment);
            $actions[] = 'seeSpamFilterSso';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanSeeProviderSso($deployment);
            $actions[] = 'seeProviderSso';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanSeeConnectionDetails($deployment);
            $actions[] = 'seeConnectionDetails';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageMailAccounts($deployment);
            $actions[] = 'manageMailAccounts';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        try {
            self::assertCanManageMailForwards($deployment);
            $actions[] = 'manageMailForwards';
        } catch (AuthorizationException) {
            // @ignoreException
        }

        return $actions;
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanManageMailAccounts(HostingDeployment $deployment): void
    {
        if (! $this->hasLegacyOrMailManagementSpec($deployment)) {
            throw new AuthorizationException('Can\'t manage mail accounts.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanSeeSpamFilterSso(HostingDeployment $deployment): void
    {
        if (! $this->hasLegacyMailSpec($deployment)) {
            throw new AuthorizationException('Can\'t see spam filter SSO.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanSeeProviderSso(HostingDeployment $deployment): void
    {
        if ($this->hasLegacyMailSpec($deployment)) {
            throw new AuthorizationException('Provider SSO is not available.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanSeeConnectionDetails(HostingDeployment $deployment): void
    {
        if (! $this->hasLegacyOrMailManagementSpec($deployment)) {
            throw new AuthorizationException('Connection details are not available.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanManageMailForwards(HostingDeployment $deployment): void
    {
        if (! $this->hasLegacyMailSpec($deployment)) {
            throw new AuthorizationException('Can\'t manage mail forwards.');
        }
    }

    private function hasLegacyMailSpec(HostingDeployment $deployment): bool
    {
        $product = $deployment->subscription->product;

        return $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
        );
    }

    private function hasLegacyOrMailManagementSpec(HostingDeployment $deployment): bool
    {
        $product = $deployment->subscription->product;

        $hasLegacyMailSpec = $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::HOSTING_LEGACY_MAIL_ONLY,
        );
        $hasMailManagementSpec = $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::HOSTING_HAS_MAIL_MANAGEMENT,
        );

        return $hasLegacyMailSpec || $hasMailManagementSpec;
    }
}
