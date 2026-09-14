<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Factories;

use RuntimeException;
use Waterfront\Domain\MailManagement\Interfaces\MailManagementDriverInterface;
use Waterfront\Domain\MailManagement\Services\MailManagementDirectAdminService;
use Waterfront\Domain\MailManagement\Services\MailManagementPlaceholderService;
use Waterfront\Domain\MailManagement\Services\MailManagementPleskService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\Hosting\Exceptions\DriverNotDefinedException;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

class MailOnlyServiceFactory
{
    public function __construct(
        private readonly MailManagementDirectAdminService $directAdminService,
        private readonly MailManagementPleskService $pleskService,
        private readonly MailManagementPlaceholderService $mailOnlyPlaceholderService,
        private readonly ProviderRepository $providerRepository,
    ) {
    }

    public function driver(?ProviderSlug $slug = null): MailManagementDriverInterface
    {
        $slug ??= $this->providerRepository->getEnabledDefaultByType(ProviderType::MAILONLY)->slug;

        return match ($slug) {
            ProviderSlug::DIRECTADMIN => $this->directAdminService,
            ProviderSlug::PLESK => $this->pleskService,
            ProviderSlug::PLACEHOLDER => $this->mailOnlyPlaceholderService,
            default => throw new RuntimeException(sprintf('Mailonly driver %s doesn\'t exist.', $slug->value)),
        };
    }

    public function getDriverFromMailServer(Server $server): string
    {
        return match ($server->type) {
            ServerType::DIRECTADMIN_MAIL => ProviderSlug::DIRECTADMIN->value,
            ServerType::PLESK => ProviderSlug::PLESK->value,
            default => throw new DriverNotDefinedException(sprintf(
                'Could not resolve a driver from a hosting server ID: {%d} with hostname: {%s}',
                $server->id,
                $server->hostname,
            )),
        };
    }
}
