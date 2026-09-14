<?php

declare(strict_types=1);

namespace Waterfront\Domain\Sitebuilder;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use UnexpectedValueException;
use Waterfront\Domain\DNS\Events\UpdateDns;
use Waterfront\Domain\DNS\Services\DnsZoneService;
use Waterfront\Domain\Hosting\DirectAdmin\Services\DirectadminUsernameBroker;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\MailManagement\Exceptions\MailOnlyException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Providers\Enums\ProviderSettingKey;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Exceptions\ServerNotFoundException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderSiteInterface;
use Waterfront\Domain\Sitebuilder\DTO\SitebuilderUserInterface;
use Waterfront\Domain\Sitebuilder\Exceptions\SitebuilderException;
use Waterfront\Domain\Sitebuilder\Factories\SitebuilderServiceFactory;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\GatewayHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class SitebuilderService
{
    public function __construct(
        private readonly SitebuilderServiceFactory $sitebuilderServiceFactory,
        private readonly DirectadminUsernameBroker $broker,
        private readonly ProviderRepository $providerRepository,
        private readonly ServerRepository $serverRepository,
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
        private readonly GatewayHelper $gatewayHelper,
        private readonly DnsZoneService $dnsZoneService,
        private readonly ConfigurationInterface $configuration,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function createSite(Subscription $subscription): bool
    {
        if (! $subscription->product->isSitebuilderProduct()) {
            throw new UnexpectedValueException(
                "Unexpected subscription provided sitebuilder subscriptions allowed given subscription with ID: {$subscription->id}",
            );
        }

        if ($subscription->domain === null) {
            $this->logger->notice(
                sprintf(
                    'Sitebuilder provision failed for subscription uuid %s. Domain is missing',
                    $subscription->uuid,
                ),
                $this->getLogContext($subscription),
            );
            throw new UnexpectedValueException('Can not provision sitebuilder because domain is missing.');
        }

        $this->logger->info('Creating sitebuilder hosting', $this->getLogContext($subscription));

        $defaultProvider = $this->sitebuilderServiceFactory->getDefaultSitebuilderProvider();
        $mailOnlyProvider = $this->providerRepository->getEnabledDefaultByType(ProviderType::MAILONLY);
        $mailOnlyServer = $this->getMailOnlyServer($mailOnlyProvider);

        $hostingDeployment = $subscription->hostingDeployment;
        if (is_null($hostingDeployment)) {
            $hostingDeployment = new HostingDeployment();
            $hostingDeployment->subscription_uuid = $subscription->uuid;
        }

        $hostingDeployment->mail_only_provider_id = $mailOnlyProvider->id;
        $hostingDeployment->mail_only_server_id = $mailOnlyServer->id;
        $hostingDeployment->directadmin_customer_username = $this->broker->generateUsername();
        $hostingDeployment->save();

        if ($this->hasSitebuilderThroughGateway($subscription->customer->email)) {
            $gatewayResult = $this->createSiteUsingGateway($subscription);
            $this->setSitebuilderDns($subscription->domain, $mailOnlyServer);

            return $gatewayResult;
        }

        $sitebuilderServer = $this->getSitebuilderServer($defaultProvider);
        $result = $this->sitebuilderServiceFactory->driver()->createSite(
            $subscription,
            $sitebuilderServer,
            $mailOnlyServer,
        );

        $hostingDeployment->basekit_server_id = $sitebuilderServer->id;
        $hostingDeployment->sitebuilder_provider_id = $defaultProvider->id;
        $hostingDeployment->save();

        $subscription->refresh();

        $hostingDeployment = $subscription->hostingDeployment;
        assert($hostingDeployment !== null);

        $hostingDeployment->provider_id = null;

        $responseBody = $result->getResponseBody();
        $ref = $responseBody['ref'] ?? null;
        assert(is_numeric($ref));
        $baseKitSiteRef = intval($ref);
        $hostingDeployment->last_created_result = json_encode($result->getResponseBody(), JSON_THROW_ON_ERROR);
        $hostingDeployment->last_created_result_received = CarbonImmutable::now();

        if ($result->getStatus() === Result::STATUS_OK && ! is_null($result->getResourceId())) {
            $hostingDeployment->basekit_user_ref = (int) $result->getResourceId();
            $hostingDeployment->basekit_site_ref = $baseKitSiteRef;
            $subscription->technical_status = TechnicalStatus::OK->value;
        } else {
            $subscription->technical_status = TechnicalStatus::FAILED->value;
        }

        $hostingDeployment->save();
        $subscription->save();

        return $result->getStatus() === Result::STATUS_OK;
    }

    public function getSite(HostingDeployment $hostingDeployment): SitebuilderSiteInterface
    {
        return $this->sitebuilderServiceFactory
            ->driver($hostingDeployment->sitebuilderProvider?->slug->value)
            ->getSite($hostingDeployment);
    }

    public function getSiteFromRef(int $siteRef, Server $server, ?string $driverSlug = null): SitebuilderSiteInterface
    {
        return $this->sitebuilderServiceFactory->driver($driverSlug)->getSiteFromRef($siteRef, $server);
    }

    public function getUserFromRef(int $userRef, Server $server, ?string $driverSlug = null): SitebuilderUserInterface
    {
        return $this->sitebuilderServiceFactory->driver($driverSlug)->getUserFromRef($userRef, $server);
    }

    public function setupSsl(SslDeployment $sslDeployment, HostingDeployment $hostingDeployment): bool
    {
        $server = $hostingDeployment->basekitServer;
        if (is_null($server)) {
            throw new ServerNotFoundException(sprintf(
                'No server found on hostingsubscription with id: %s',
                $hostingDeployment->id,
            ));
        }

        Log::info(
            'Setting up sitebuilder SSL',
            [
                LoggingContextKeys::META => [
                    'hosting_subscription_uuid' => $hostingDeployment->subscription_uuid,
                    'ssl_subscription_uuid' => $sslDeployment->subscription_uuid,
                ],
                LoggingContextKeys::SERVER_ID => $server->id,
            ],
        );

        $result = $this->sitebuilderServiceFactory->driver($hostingDeployment->sitebuilderProvider?->slug->value)->setupSsl(
            $sslDeployment,
            $server,
        );

        return $result->getStatus() === Result::STATUS_OK;
    }

    public function terminate(Subscription $subscription): void
    {
        if (! $subscription->product->isSitebuilderProduct()) {
            throw new UnexpectedValueException(
                "Unexpected subscription provided sitebuilder subscriptions allowed given subscription with ID: {$subscription->id}",
            );
        }

        $hostingDeployment = $subscription->hostingDeployment;

        if (is_null($hostingDeployment)) {
            throw new UnexpectedValueException(sprintf(
                'Subscription %s (%d) has no hosting deployment',
                $subscription->domain,
                $subscription->id,
            ));
        }

        $server = $hostingDeployment->basekitServer;

        if (is_null($server)) {
            throw new ServerNotFoundException(sprintf(
                'No server found for hostingsubcription with id: %s',
                $hostingDeployment->id,
            ));
        }

        Log::info(
            'Terminating sitebuilder hosting',
            [
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                LoggingContextKeys::SERVER_ID => $server->id,
            ],
        );

        $this->sitebuilderServiceFactory->driver($hostingDeployment->sitebuilderProvider?->slug->value)->deleteSite(
            $hostingDeployment,
            $server,
        );
    }

    public function getSsoUrl(HostingDeployment $hostingDeployment): string
    {
        $sitebuilderSubscription = $hostingDeployment->subscription;
        $domain = $sitebuilderSubscription->domain;

        if ($domain === null) {
            throw new UnexpectedValueException(sprintf(
                'Hostingdeployment with id: %s, has no domain on subscription.',
                $hostingDeployment->id,
            ));
        }

        if ($this->gatewayHelper->hasSitebuilderDeploymentUsingGateway($sitebuilderSubscription)) {
            $getSsoRequest = new GetSitebuilderSsoRequest(
                context: Uuid::fromString($sitebuilderSubscription->uuid),
                tagUuid: Uuid::fromString($sitebuilderSubscription->uuid),
            );

            $result = $this->provisionGateway->request($getSsoRequest);

            if (! $result instanceof SitebuilderSsoResult || $result->failed) {
                throw new SitebuilderException(
                    message: sprintf(
                        'Error when trying to fetch SSO url for subscription [%s] through gateway.',
                        $sitebuilderSubscription->uuid,
                    ),
                    previous: $result->exception,
                );
            }

            // @phpstan-ignore return.type (PHPStan doesn't support parent::$prop::get() yet, see phpstan/phpstan#12336)
            return $result->ssoUrl;
        }

        $basekitUserRef = $hostingDeployment->basekit_user_ref;

        if (is_null($basekitUserRef)) {
            throw new UnexpectedValueException(sprintf(
                'Hostingdeployment with id: %s, and domain: %s, does not have baseKit_user_ref.',
                $hostingDeployment->id,
                $domain,
            ));
        }

        $basekitSiteRef = $hostingDeployment->basekit_site_ref;

        if ($basekitSiteRef === null) {
            throw new UnexpectedValueException(sprintf(
                'Hostingdeployment with id: %s, and domain: %s, does not have baseKit_site_ref.',
                $hostingDeployment->id,
                $domain,
            ));
        }

        $server = $hostingDeployment->basekitServer;
        if ($server === null) {
            throw new ServerNotFoundException(sprintf(
                'No server found for hostingDeployment id: %s',
                $hostingDeployment->id,
            ));
        }

        if ($hostingDeployment->sitebuilder_provider_id === null) {
            throw new UnexpectedValueException(sprintf(
                'No provider found for hostingDeployment id: %s',
                $hostingDeployment->id,
            ));
        }

        return $this->sitebuilderServiceFactory->driver($hostingDeployment->sitebuilderProvider?->slug->value)->getSsoUrl(
            $server,
            $domain,
            $basekitUserRef,
            $basekitSiteRef,
        );
    }

    public function getMailOnlyServer(Provider $mailOnlyProvider): Server
    {
        switch ($mailOnlyProvider->slug) {
            case ProviderSlug::PLESK:
                return $this->serverRepository->findAvailableServer(ServerType::PLESK);
            case ProviderSlug::DIRECTADMIN:
                $serverId = (int) $this->providerRepository->getSettingByKey(
                    $mailOnlyProvider,
                    ProviderSettingKey::DEFAULTSERVERID,
                )->value;

                return Server::where('id', $serverId)->firstOrFail();
            default:
                throw new MailOnlyException(
                    sprintf(
                        'Unknown mail only provider %s',
                        $mailOnlyProvider->slug->value,
                    ),
                );
        }
    }

    public function getSitebuilderServer(Provider $sitebuilderProvider): Server
    {
        $serverId = (int) $this->providerRepository->getSettingByKey(
            $sitebuilderProvider,
            ProviderSettingKey::DEFAULTSERVERID,
        )->value;

        return Server::where('id', $serverId)->firstOrFail();
    }

    public function update(Subscription $subscription): void
    {
        $packages = $this->getPackages($subscription);
        $provisionRequest = new UpdateSitebuilderRequest(
            Uuid::fromString($subscription->uuid),
            Uuid::fromString($subscription->uuid),
            $packages,
            $subscription->contract_period,
        );

        $provisioningResult = $this->provisionGateway->request($provisionRequest);

        if ($provisioningResult->failed) {
            $this->logger->info(
                sprintf(
                    'Sitebuilder update failed for subscription uuid %s: with packages: %s',
                    $subscription->uuid,
                    implode(', ', $packages),
                ),
                $this->getLogContext($subscription) + [LoggingContextKeys::REQUEST_DATA => implode(', ', $packages)],
            );

            throw new Exception('Sitebuilder provision failed');
        }

        $this->logger->info(
            'Sitebuilder update finished',
            $this->getLogContext($subscription) + [LoggingContextKeys::REQUEST_DATA => implode(', ', $packages)],
        );
    }

    // Because of upcoming release we disable gateway provisioning for sitebuilder
    public function hasSitebuilderThroughGateway(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        return str_contains($email, '@sandwave.io') || str_contains($email, '@yourhosting.nl');
    }

    public function getProviderSlug(Subscription $subscription): string
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::HOSTING) {
            return Provider::where('default', true)
                ->where('type', ProviderType::SITEBUILDER)
                ->firstOrFail()
                ->slug
                ->value;
        }

        if ($subscription->hostingDeployment !== null) {
            return $subscription->hostingDeployment->sitebuilderProvider?->slug->value ?? ProviderSlug::BASEKIT->value;
        }

        $this->logger->notice(sprintf(
            'Subscription with id: %d was a Sitebuilder hosting deployment but was not coupled to a SiteBuilder provider.',
            $subscription->id,
        ));

        $default = Provider::where('default', true)->where('type', ProviderType::SITEBUILDER)->first();

        if ($default instanceof Provider) {
            return $default->slug->value;
        }

        return 'unknown';
    }

    /**
     * @throws Exception
     *
     * @return int[]
     */
    private function getPackages(Subscription $subscription): array
    {
        $subscription->loadMissing(['children.product.productSpecs']);

        $packages = [];
        $subscriptionBasekitProductSpec = $subscription
            ->product
            ->productSpecs
            ->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)
            ->first();

        if ($subscriptionBasekitProductSpec === null) {
            throw new Exception('Can not provision sitebuilder because package ref is missing.');
        }

        $packages[] = (int) $subscriptionBasekitProductSpec->value;

        foreach ($subscription->children as $child) {
            $spec = $child
                ->product
                ->productSpecs
                ->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)
                ->first();
            if ($spec === null) {
                continue;
            }

            $packages[] = (int) $spec->value;
        }

        return $packages;
    }

    private function createSiteUsingGateway(Subscription $subscription): bool
    {
        $subscription->loadMissing(['customer', 'product.productSpecs', 'children.product.productSpecs']);
        /** @var array<int> $packages */
        $packages = [];
        $subscriptionBasekitProductSpec = $subscription
            ->product
            ->productSpecs
            ->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)
            ->first();

        if ($subscriptionBasekitProductSpec === null) {
            $this->logger->error(
                sprintf(
                    'Sitebuilder provision failed for subscription uuid %s: main product package ref is missing',
                    $subscription->uuid,
                ),
                $this->getLogContext($subscription),
            );
            throw new Exception('Can not provision sitebuilder because package ref is missing.');
        }

        $packages[] = (int) $subscriptionBasekitProductSpec->value;

        foreach ($this->getAdministrativelyActiveChildren($subscription) as $child) {
            $spec = $child
                ->product
                ->productSpecs
                ->where('name', ProductSpecName::BASEKIT_PACKAGE_REFERENCE->value)
                ->first();
            if ($spec === null) {
                continue;
            }

            $packages[] = (int) $spec->value;
        }

        assert($subscription->domain !== null);
        $request = new CreateSitebuilderRequest(
            domain: $subscription->domain,
            packages: $packages,
            firstname: $subscription->customer->first_name,
            lastname: $subscription->customer->last_name,
            email: $subscription->customer->email,
            contractPeriod: $subscription->contract_period,
            context: Uuid::fromString($subscription->uuid),
        );

        $request->tag = Uuid::fromString($subscription->uuid);

        $result = $this->provisionGateway->request($request);

        if ($result->failed) {
            $this->logger->info(
                sprintf(
                    'Sitebuilder provision failed for subscription uuid %s: with packages: %s',
                    $subscription->uuid,
                    implode(', ', $packages),
                ),
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::REQUEST_DATA => implode(', ', $packages),
                ],
            );

            $subscription->technical_status = TechnicalStatus::FAILED->value;
            $subscription->save();

            foreach ($this->getAdministrativelyActiveChildren($subscription) as $child) {
                $child->technical_status = TechnicalStatus::FAILED->value;
                $child->save();
            }

            throw new Exception(message: 'Sitebuilder provision failed', previous: $result->exception);
        }

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        foreach ($this->getAdministrativelyActiveChildren($subscription) as $child) {
            $child->technical_status = TechnicalStatus::OK->value;
            $child->save();
        }

        return true;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function getAdministrativelyActiveChildren(Subscription $subscription): Collection
    {
        return $subscription->children->filter(
            fn (Subscription $child) => $child->administrative_status !== AdministrativeStatus::ARCHIVED->value,
        );
    }

    /**
     * @return mixed[]
     */
    private function getLogContext(Subscription $subscription): array
    {
        return [
            LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
            LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
        ];
    }

    private function setSitebuilderDns(string $domain, Server $mailOnlyServer): void
    {
        $dnsChanges = $this->dnsZoneService->getExternalHostingDnsRecords(
            domain: $domain,
            ipv4: $this->configuration->getAsString('basekit.ipv4'),
            ipv6: null,
            ipv4Mail: $mailOnlyServer->ipv4,
            ipv6Mail: $mailOnlyServer->ipv6,
        );

        $this->eventDispatcher->dispatch(new UpdateDns($domain, $dnsChanges));
    }
}
