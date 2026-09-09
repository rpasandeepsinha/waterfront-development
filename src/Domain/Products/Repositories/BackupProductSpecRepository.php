<?php

declare(strict_types=1);

namespace Waterfront\Domain\Products\Repositories;

use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;

class BackupProductSpecRepository
{
    public function __construct(
        private readonly ProductSpecRepository $productSpecRepository,
    ) {
    }

    public function getCloudStorage(Product $product): ?float
    {
        return $this->productSpecRepository
            ->getFloatValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_CLOUD_STORAGE_GB
            );
    }

    public function getLocalStorage(Product $product): ?float
    {
        return $this->productSpecRepository
            ->getFloatValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_LOCAL_STORAGE_GB
            );
    }

    public function getMobileDevices(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_MOBILE_DEVICES
            );
    }

    public function getWorkstations(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_WORKSTATIONS
            );
    }

    public function getServers(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_SERVERS
            );
    }

    public function getVirtualMachines(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_VMS
            );
    }

    public function getHostingServers(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_HOSTING_SERVERS
            );
    }

    public function getM365Seats(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_M365_SEATS
            );
    }

    public function getM365SharepointSites(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_M365_SHAREPOINT_SITES
            );
    }

    public function getM365Teams(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_M365_TEAMS
            );
    }

    public function getGoogleWorkspaceSeats(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_GOOGLE_WORKSPACE_SEATS
            );
    }

    public function enableGoogleWorkspaceDrive(Product $product): ?bool
    {
        $spec = $this->productSpecRepository
            ->findBySpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_ENABLE_GOOGLE_WORKSPACE_DRIVE->value
            );

        if ($spec === null) {
            return null;
        }

        $value = (string) $spec->value;

        return $value === '1';
    }

    public function getWebsites(Product $product): ?int
    {
        return $this->productSpecRepository
            ->getIntegerValueOfSpecification(
                product: $product,
                specification: ProductSpecName::ACRONIS_WEBSITES
            );
    }
}
