<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Interfaces;

interface OfferingItemsRequest
{
    public ?float $cloudStorageInGb { get; }

    public ?float $localStorageInGb { get; }

    public ?int $mobileDevices { get; }

    public ?int $workStations { get; }

    public ?int $vms { get; }

    public ?int $servers { get; }

    public ?int $hostingServers { get; }

    public ?int $m365Seats { get; }

    public ?int $m365SharepointSites { get; }

    public ?int $m365Teams { get; }

    public ?int $googleWorkspaceSeats { get; }

    public ?bool $enableGoogleWorkspaceDrive { get; }

    public ?int $websites { get; }
}
