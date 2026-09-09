<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Services;

use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Waterfront\Domain\Backup\Events\CreateBackup;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Hosting\Events\CreateHosting;
use Waterfront\Domain\MailManagement\Events\CreateMailOnlyHosting;
use Waterfront\Domain\ManualProvisioning\Events\DispatchCreateManualProvisioning;
use Waterfront\Domain\Microsoft365\Events\CreateMicrosoft365;
use Waterfront\Domain\Notes\Actions\StoreNoteAction;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\MetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\OsMetaData;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\BackupProductSpecRepository;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\ResellerHosting\Jobs\CreateResellerHostingJob;
use Waterfront\Domain\Sitebuilder\Events\CreateSitebuilder;
use Waterfront\Domain\Ssl\Events\CreateSsl;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Domain\VPS\Events\CreateVps;
use Waterfront\Domain\VPS\Interfaces\VirtualMachineDeploymentRepositoryInterface;
use Waterfront\Support\Jobs\AbstractQueueableJob;

readonly class ProvisionService
{
    public function __construct(
        private EventSubscriptionDataBuilder $builder,
        private VirtualMachineDeploymentRepositoryInterface $vmSubscriptionRepository,
        private CartSerializerFactory $cartSerializerFactory,
        private EventDispatcher $eventDispatcher,
        private JobDispatcher $jobDispatcher,
        private BackupProductSpecRepository $backupProductSpecRepository,
        private StoreNoteAction $storeNoteAction,
    ) {
    }

    /**
     * @param array<Subscription> $subscriptions
     */
    public function provision(array $subscriptions): void
    {
        $m365Subscriptions = array_filter($subscriptions, fn (Subscription $subscription) => $subscription->product->productGroup->slug === ProductGroupType::MICROSOFT_365);

        if (count($m365Subscriptions) > 0) {
            $this->provisionMicrosoft365($m365Subscriptions);
        }

        $subscriptions = array_filter($subscriptions, fn (Subscription $subscription) => $subscription->product->productGroup->slug !== ProductGroupType::MICROSOFT_365);

        foreach ($subscriptions as $subscription) {
            $event = match ($subscription->product->productGroup->slug) {
                ProductGroupType::BACKUP => $this->provisionBackup($subscription),
                ProductGroupType::EXTENSION => $this->provisionExtension($subscription),
                ProductGroupType::REDIRECT => $this->provisionRedirect($subscription),
                ProductGroupType::OTHER => $this->provisionOther($subscription),
                ProductGroupType::SSL => $this->provisionSsl($subscription),
                ProductGroupType::DNS => $this->provisionDns($subscription),
                ProductGroupType::RESELLER_HOSTING => $this->provisionResellerHosting($subscription),
                ProductGroupType::VPS => $this->provisionCloudStackVirtualMachine($subscription),
                ProductGroupType::MANUAL_SUBSCRIPTION => new DispatchCreateManualProvisioning($subscription),
                ProductGroupType::HOSTING => $this->provisionHosting($subscription),
                ProductGroupType::DOMAIN_EXPANSION,
                ProductGroupType::RESELLER_DISCOUNT,
                ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
                ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
                ProductGroupType::CLOUDSTACK_VOLUME,
                ProductGroupType::CLOUDSTACK_OS,
                ProductGroupType::MICROSOFT_365,
                ProductGroupType::ONE_TIME_SERVICE,
                ProductGroupType::VOLUME_DISCOUNT,
                ProductGroupType::ADD_ON => null
            };

            if ($event === null) {
                continue;
            }

            if (in_array($subscription->technical_status, [TechnicalStatus::OK->value, DomainStatus::ACTIVE->value, DomainStatus::REQUESTED->value, TechnicalStatus::PENDING->value], true)) {
                continue;
            }

            if ($event instanceof AbstractQueueableJob) {
                $this->jobDispatcher->dispatch($event);
                continue;
            }

            $this->eventDispatcher->dispatch($event);
        }
    }

    /**
     * @param array<Subscription> $subscriptions
     */
    private function provisionMicrosoft365(array $subscriptions): void
    {
        $this->eventDispatcher->dispatch(
            new CreateMicrosoft365($subscriptions)
        );
    }

    private function provisionBackup(Subscription $subscription): CreateBackup
    {
        $subscription->loadMissing(['customer', 'product']);
        $customer = $subscription->customer;
        $product = $subscription->product;

        $language = match ($customer->locale) {
            Locale::DUTCH->value => Language::DUTCH,
            Locale::GERMAN->value => Language::GERMAN,
            Locale::FRENCH->value => Language::FRENCH,
            Locale::SPANISH->value => Language::SPANISH,
            default => Language::ENGLISH,
        };

        $createRequest = new CreateBackupRequest(
            tagUuid: Uuid::fromString($subscription->uuid),
            email: $customer->email,
            firstname: $customer->first_name,
            lastname: $customer->last_name,
            cloudStorageInGb: $this->backupProductSpecRepository->getCloudStorage($product),
            localStorageInGb: $this->backupProductSpecRepository->getLocalStorage($product),
            language: $language,
            mobileDevices: $this->backupProductSpecRepository->getMobileDevices($product),
            workStations: $this->backupProductSpecRepository->getWorkstations($product),
            servers: $this->backupProductSpecRepository->getServers($product),
            vms: $this->backupProductSpecRepository->getVirtualMachines($product),
            hostingServers: $this->backupProductSpecRepository->getHostingServers($product),
            m365Seats: $this->backupProductSpecRepository->getM365Seats($product),
            m365SharepointSites: $this->backupProductSpecRepository->getM365SharepointSites($product),
            m365Teams: $this->backupProductSpecRepository->getM365Teams($product),
            googleWorkspaceSeats: $this->backupProductSpecRepository->getGoogleWorkspaceSeats($product),
            enableGoogleWorkspaceDrive: $this->backupProductSpecRepository->enableGoogleWorkspaceDrive($product),
            websites: $this->backupProductSpecRepository->getWebsites($product),
        );

        return new CreateBackup(subscription: $subscription, createBackupRequest: $createRequest);
    }

    private function provisionExtension(Subscription $subscription): CreateDomain
    {
        $domain = $subscription->domain;

        assert(is_string($domain));

        $metaData = $this->builder->buildExtensionMetaData($subscription->orderLineItem?->meta_data);

        $transferSecret = $metaData->transferSecret ?? $subscription->orderLineItem?->transfer_secret;

        if ($transferSecret !== 'deferred_transfer') {
            $order = $subscription->orderLineItem?->order;
            if ($order !== null) {
                $filteredLines = $order->lineItems->filter(fn (OrderLineItem $line) => $line->domain === $domain);
                foreach ($filteredLines as $filteredLine) {
                    $filteredLine->load(['children']);
                    if (count($filteredLine->children) === 0) {
                        continue;
                    }
                    $item = $filteredLine->children->filter(fn (OrderLineItem $line) => $line->product?->slug === 'transfer_service');
                    if (count($item) === 0) {
                        continue;
                    }

                    $noteMessage = sprintf(
                        'Transfer_secret changed from %s to deferred_transfer',
                        $transferSecret,
                    );

                    $this->storeNoteAction->execute($noteMessage, $subscription);

                    $transferSecret = 'deferred_transfer';
                }
            }
        }

        $isUsingPrivateWhois = $this->builder->buildPrivateWhoisStatus($subscription, $metaData);
        $dnssecEnabled = $this->builder->buildDnssecEnabled($subscription);
        $domainDeployment = $this->builder->buildDomainDeployment($subscription, $dnssecEnabled, $isUsingPrivateWhois, $transferSecret);
        $this->builder->buildOwnerAssociation($subscription, $metaData->contactId ?? 0);

        return
            new CreateDomain(
                $domain,
                $subscription,
                $domainDeployment->refresh(),
            )
        ;
    }

    private function provisionHosting(Subscription $subscription): CreateMailOnlyHosting|CreateSitebuilder|CreateHosting
    {
        if ($subscription->product->isSitebuilderProduct()) {
            assert(is_string($subscription->domain));

            return
                new CreateSitebuilder(
                    $subscription->customer->name,
                    $subscription->customer->email,
                    $subscription
                );
        }

        if ($subscription->product->isMailOnlyServer()) {
            assert(is_string($subscription->domain));

            return
                new CreateMailOnlyHosting(
                    $subscription->customer->name,
                    $subscription->customer->email,
                    $subscription
                );
        }

        return new CreateHosting(
            subscriptionUuid: $subscription->uuid,
            technicalStatus: $subscription->technical_status,
            contactPersonName: $subscription->customer->name,
            contactEmail: $subscription->customer->email,
            domain: $subscription->domain,
            customer: $subscription->customer,
            product: $subscription->product,
            serverId: $subscription->orderLineItem?->server,
        );
    }

    private function provisionSsl(Subscription $subscription): CreateSsl
    {
        $domain = $subscription->domain;
        assert(is_string($domain));

        $this->builder->buildSslDeployment($subscription);

        if ($subscription->sslDeployment === null) {
            throw new RuntimeException('SSL deployment can not be null');
        }

        return new CreateSsl(
            domain: $domain,
            period: $subscription->contract_period,
            sslDeployment: $subscription->sslDeployment,
            csr: null
        );
    }

    private function provisionDns(Subscription $subscription): CreateDns
    {
        $domain = $subscription->domain;
        assert(is_string($domain));

        $this->builder->buildDnsDeployment($subscription);

        return new CreateDns(
            $subscription->uuid,
            $domain,
        );
    }

    private function provisionResellerHosting(Subscription $subscription): CreateResellerHostingJob
    {
        return new CreateResellerHostingJob(
            $subscription->uuid,
            $subscription->technical_status,
            $subscription->customer->name,
            $subscription->customer->email,
            null,
            $subscription->customer,
            $subscription->product
        );
    }

    private function provisionCloudStackVirtualMachine(Subscription $subscription): CreateVps
    {
        $osSubscription = $this->vmSubscriptionRepository
            ->getOsSubscriptionChildFromSubscriptionUuid($subscription->uuid);
        $osSubscriptionMetaData = null;
        if ($osSubscription->orderLineItem?->meta_data !== null) {
            $osSubscriptionMetaData = $this->getSubscriptionMetaData($osSubscription->orderLineItem->meta_data);
        }

        return
            new CreateVps(
                $subscription->uuid,
                $osSubscriptionMetaData?->sshKeyUuid
            )
        ;
    }

    private function provisionRedirect(Subscription $subscription): null
    {
        $domain = $subscription->domain;
        assert(is_string($domain));

        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        return null;
    }

    private function provisionOther(Subscription $subscription): null
    {
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->save();

        return null;
    }

    private function getSubscriptionMetaData(string $metaData): OsMetaData
    {
        $metaData = $this->cartSerializerFactory->get()
            ->deserialize($metaData, MetaData::class, 'json');

        assert($metaData instanceof OsMetaData);

        return $metaData;
    }
}
