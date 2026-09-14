<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Actions;

use Waterfront\Apps\API\Compass\DTO\UpdateHostingDeploymentDto;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Servers\Enums\ServerType;

class UpdateHostingDeploymentAction
{
    public function __construct(
        private readonly ProviderRepository $providerRepository,
        private readonly ServerRepository $serverRepository,
    ) {
    }

    public function execute(UpdateHostingDeploymentDto $dto): void
    {
        $provider = $this->providerRepository->getBySlug(ProviderSlug::from($dto->provider));
        $server = $this->serverRepository->getById($dto->serverId);

        $hostingDeployment = $dto->hostingDeployment;

        switch ($provider->type) {
            case ProviderType::SITEBUILDER:
                $mailProvider = $this->providerRepository->getByType(
                    ProviderType::MAILONLY,
                    ProviderSlug::from($dto->mailProvider ?? ''),
                );
                $mailServer = $dto->mailServerId !== null ? $this->serverRepository->getById($dto->mailServerId) : null;
                $hostingDeployment->mail_only_provider_id = $mailProvider->id;
                $hostingDeployment->mail_only_server_id = $mailServer?->id;
                $hostingDeployment->sitebuilder_provider_id = $provider->id;
                $hostingDeployment->basekit_server_id = $server->id;
                $hostingDeployment->basekit_user_ref = $dto->basekitUserRef;
                $hostingDeployment->basekit_site_ref = $dto->basekitSiteRef;
                $hostingDeployment->directadmin_customer_username = null;
                $hostingDeployment->plesk_customer_id = null;
                $hostingDeployment->plesk_customer_username = null;
                $hostingDeployment->server_id = null;
                $hostingDeployment->provider_id = null;

                break;
            case ProviderType::MAILONLY:
                $mailProvider = $this->providerRepository->getByType(
                    ProviderType::MAILONLY,
                    ProviderSlug::from($dto->provider),
                );
                $hostingDeployment->mail_only_provider_id = $mailProvider->id;
                $hostingDeployment->mail_only_server_id = $server->id;
                $hostingDeployment->sitebuilder_provider_id = null;
                $hostingDeployment->basekit_server_id = null;
                $hostingDeployment->basekit_user_ref = null;
                $hostingDeployment->basekit_site_ref = null;
                $hostingDeployment->directadmin_customer_username = null;
                $hostingDeployment->plesk_customer_id = null;
                $hostingDeployment->plesk_customer_username = null;
                $hostingDeployment->server_id = null;
                $hostingDeployment->provider_id = null;
                break;
            default:
                $hostingDeployment->plesk_customer_username = $provider->slug === ProviderSlug::PLESK
                    ? $dto->username
                    : null;
                $hostingDeployment->directadmin_customer_username = $provider->slug === ProviderSlug::DIRECTADMIN
                    ? $dto->username
                    : null;
                $hostingDeployment->plesk_customer_id = $provider->slug === ProviderSlug::PLESK
                    ? $dto->pleskCustomerId
                    : null;
                $hostingDeployment->server_id =
                    $server->type === ServerType::DIRECTADMIN || $server->type === ServerType::PLESK
                        ? $server->id
                        : null;
                $hostingDeployment->provider_id = $provider->type === ProviderType::HOSTING ? $provider->id : null;
                $hostingDeployment->mail_only_provider_id = null;
                $hostingDeployment->mail_only_server_id = null;
                $hostingDeployment->sitebuilder_provider_id = null;
                $hostingDeployment->basekit_server_id = null;
                $hostingDeployment->basekit_user_ref = null;
                $hostingDeployment->basekit_site_ref = null;
                break;
        }

        $hostingDeployment->save();
    }
}
