<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Acronis\Helpers;

use Waterfront\Domain\Provision\Backup\Acronis\Enums\OfferingItemPropertyName;
use Waterfront\Domain\Provision\Backup\Dto\OfferingItemDto;
use Waterfront\Domain\Provision\Backup\Interfaces\OfferingItemsRequest;
use Waterfront\Infra\AcronisClient\Enums\OfferingItems\OfferingItemStatus;
use Waterfront\Support\Helpers\ByteHelper;

class AcronisOfferingItemHelper
{
    /**
     * @return OfferingItemDto[]
     */
    public function getOfferingItemDto(OfferingItemsRequest $provisionData): array
    {
        $offeringItems = [];

        if ($provisionData->cloudStorageInGb !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::CLOUD_STORAGE,
                quota: ByteHelper::giBToBytes($provisionData->cloudStorageInGb),
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->vms !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::VMS,
                quota: $provisionData->vms,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->servers !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::SERVERS,
                quota: $provisionData->servers,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->workStations !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::WORKSTATIONS,
                quota: $provisionData->workStations,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->mobileDevices !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::MOBILES,
                quota: $provisionData->mobileDevices,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->localStorageInGb !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::LOCAL_STORAGE,
                quota: ByteHelper::giBToBytes($provisionData->localStorageInGb),
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->hostingServers !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::HOSTING_SERVERS,
                quota: $provisionData->hostingServers,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->m365Seats !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::M365_SEATS,
                quota: $provisionData->m365Seats,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->m365SharepointSites !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::M365_SHAREPOINT_SITES,
                quota: $provisionData->m365SharepointSites,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->m365Teams !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::M365_TEAMS,
                quota: $provisionData->m365Teams,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->googleWorkspaceSeats !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::GOOGLE_WORKSPACE_SEATS,
                quota: $provisionData->googleWorkspaceSeats,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        if ($provisionData->enableGoogleWorkspaceDrive !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::GOOGLE_TEAM_DRIVE,
                quota: null,
                status: $provisionData->enableGoogleWorkspaceDrive
                    ? OfferingItemStatus::ACTIVE
                    : OfferingItemStatus::NOT_ACTIVE,
            );
        }

        if ($provisionData->websites !== null) {
            $offeringItems[] = new OfferingItemDto(
                propertyName: OfferingItemPropertyName::WEBSITES,
                quota: $provisionData->websites,
                status: OfferingItemStatus::ACTIVE,
            );
        }

        return $offeringItems;
    }
}
