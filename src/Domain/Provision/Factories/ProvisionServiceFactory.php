<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Factories;

use Waterfront\Domain\Provision\Backup\Services\BackupProvisionService;
use Waterfront\Domain\Provision\DomainNames\Coupling\Services\DomainNameCoupleService;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\UnknownProvisionTypeException;
use Waterfront\Domain\Provision\Hosting\Services\HostingProvisionService;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Microsoft365\Services\Microsoft365ProvisionService;
use Waterfront\Domain\Provision\Redirects\Services\RedirectProvisionService;
use Waterfront\Domain\Provision\Sitebuilder\Services\SitebuilderProvisionService;

class ProvisionServiceFactory
{
    public function __construct(
        private readonly HostingProvisionService $hostingProvisionService,
        private readonly Microsoft365ProvisionService $microsoft365ProvisionService,
        private readonly DomainNameCoupleService $domainNameCoupleService,
        private readonly SitebuilderProvisionService $sitebuilderProvisionService,
        private readonly BackupProvisionService $backupProvisionService,
        private readonly RedirectProvisionService $redirectProvisionService,
    ) {
    }

    /**
     * @throws UnknownProvisionTypeException
     */
    public function create(ProvisionType $type): ProvisionServiceInterface
    {
        return match ($type) {
            ProvisionType::HOSTING => $this->hostingProvisionService,
            ProvisionType::M365 => $this->microsoft365ProvisionService,
            ProvisionType::DOMAIN_NAME_COUPLING => $this->domainNameCoupleService,
            ProvisionType::SITEBUILDER => $this->sitebuilderProvisionService,
            ProvisionType::BACKUP => $this->backupProvisionService,
            ProvisionType::REDIRECT => $this->redirectProvisionService,
            default => throw new UnknownProvisionTypeException($type),
        };
    }
}
