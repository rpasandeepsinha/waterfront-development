<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions;

use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Ssl\Services\CertificateRetriever;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\RtrClient\Services\RtrErrorParseService;

/**
 * @mixin Subscription
 *
 * @property Subscription $resource
 */
class SubscriptionResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        $type = $this->product->productGroup->slug;

        /** @var GetNextInvoicePriceAction $nextInvoicePriceAction */
        $nextInvoicePriceAction = Container::getInstance()->make(GetNextInvoicePriceAction::class);

        /** @var SubscriptionPolicy $subscriptionPolicy */
        $subscriptionPolicy = Container::getInstance()->make(SubscriptionPolicy::class);

        /** @var TransferService $transferService */
        $transferService = Container::getInstance()->make(TransferService::class);

        /** @var HostingService $hostingService */
        $hostingService = Container::getInstance()->make(HostingService::class);

        /** @var HostingDeploymentService $hostingDeploymentService */
        $hostingDeploymentService = Container::getInstance()->make(HostingDeploymentService::class);

        $serviceProvider = $this->getServiceProvider($this->resource);

        $resource = [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer_id' => $this->customer_id,
            'start_date' => $this->start_date->toW3cString(),
            'end_date' => $this->end_date->toW3cString(),
            'contract_period' => $this->contract_period,
            'billing_period' => $this->billing_period,
            'technical_status' => $this->technical_status,
            'administrative_status' => $this->administrative_status,
            'active_status' => DetermineSubscriptionActiveStatusHelper::resolve($this->resource),
            'domain' => $this->domain,
            'in_transfer' => $transferService->hasOpenTransfer($this->resource),
            'type' => $type,
            'product_name' => $this->resource->product->name,
            'product_slug' => $this->resource->product->slug,
            'available_actions' => $subscriptionPolicy->getAvailableActions($this->resource),
            'children' => $this->resource
                ->children()
                ->whereNotIn('administrative_status', [
                    ...AdministrativeStatus::administrativelyEnded(),
                    AdministrativeStatus::ARCHIVING->value,
                ])
                ->get(['id', 'product_uuid']),
            'has_custom_nameservers' => $this->hasCustomNameservers(),
            'has_hosting' => $this->hasHostingSubscription($this->resource),
            'service_provider' => $serviceProvider,
            'parent_subscription_id' => $this->resource->parent_subscription_id,
            'next_invoice' => [
                'date' => $this->resource->next_billing_date->format(DateTimeFormat::DATE),
                'price' => $nextInvoicePriceAction->execute($this->resource)->netPrice,
            ],
        ];

        if ($type === ProductGroupType::SSL && $this->sslDeployment?->provider->slug !== ProviderSlug::PLACEHOLDER) {
            $resource = $this->addCertificates($resource);

            if ($this->resource->sslDeployment !== null) {
                $resource['has_custom_csr'] = $this->resource->sslDeployment->custom_csr;
                $resource['has_reissued'] = $this->resource->sslDeployment->has_reissued;
                $resource['last_status'] = $this->resource->sslDeployment->status ?? 'UNKNOWN';
            }
        }

        if ($type === ProductGroupType::HOSTING) {
            $resource['hosting_provider'] = $hostingService->getProviderSlug($this->resource);

            if ($this->resource->hostingDeployment !== null) {
                if ($this->resource->hostingDeployment->server !== null) {
                    $resource['server_name'] = $this->resource->hostingDeployment->server->hostname;
                    $resource['username'] = $hostingDeploymentService->getUsername($this->resource->hostingDeployment);
                    $resource['ftps_host'] = $this->resource->hostingDeployment->server->hostname;
                }
            }
        }

        if ($type === ProductGroupType::EXTENSION) {
            $domainDeployment = $this->resource->domainDeployment;
            if ($domainDeployment !== null) {
                $resource['domain_contact_id'] = $domainDeployment->contact_owner_id;

                $failStatuses = [TechnicalStatus::FAILED->value, DomainStatus::FAILED->value];
                $rtrFailedResponse = 'Bad Request:';

                if (
                    $domainDeployment->last_result !== null
                    && in_array($this->resource->technical_status, $failStatuses, true)
                    && str_starts_with($domainDeployment->last_result, $rtrFailedResponse)
                ) {
                    $errorParseService = Container::getInstance()->make(RtrErrorParseService::class);
                    $resource['last_result'] = $errorParseService->getTranslatedRtrError($domainDeployment->last_result);
                }

                if ($serviceProvider === ProviderSlug::PLACEHOLDER->value) {
                    /** @var DnsService $dnsService */
                    $dnsService = Container::getInstance()->make(DnsService::class);
                    $resource['has_dns_zone'] = $this->resource->domain === null
                        ? false
                        : $dnsService->hasDnsZone($this->resource->domain);
                }
            }
        }

        return $resource;
    }

    private function getServiceProvider(Subscription $subscription): ?string
    {
        $getHostingProvider = function (Subscription $subscription) {
            /** @var HostingService $hostingService */
            $hostingService = Container::getInstance()->make(HostingService::class);

            return $hostingService->getProviderSlug($subscription);
        };

        return match ($this->product->productGroup->slug) {
            ProductGroupType::HOSTING => $getHostingProvider($subscription),
            ProductGroupType::EXTENSION => $this->domainDeployment?->provider->slug->value,
            ProductGroupType::SSL => $subscription->sslDeployment?->provider->slug->value,
            ProductGroupType::MANUAL_SUBSCRIPTION => 'mail-notification',
            default => null,
        };
    }

    /**
     * @param array<mixed> $resource
     *
     * @return array<mixed>
     */
    private function addCertificates(array $resource): array
    {
        if ($this->sslDeployment === null) {
            return $resource;
        }

        /** @var CertificateRetriever $retriever */
        $retriever = Container::getInstance()->make(CertificateRetriever::class);
        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = Container::getInstance()->make(UrlGenerator::class);

        $resource['certificates'] = [];
        foreach ($retriever->getAvailableCertificateTypes($this->sslDeployment) as $name => $extension) {
            $resource['certificates'][$name] = $urlGenerator->route('partners.ssl.download', [
                'type' => $extension,
                'uuid' => $this->uuid,
            ]);
        }

        return $resource;
    }

    private function hasHostingSubscription(Subscription $subscription): bool
    {
        return $subscription->product->productGroup->slug === ProductGroupType::HOSTING;
    }

    private function hasCustomNameservers(): bool
    {
        if ($this->domain === null) {
            return false;
        }

        /** @var SubscriptionRepository $repository */
        $repository = Container::getInstance()->make(SubscriptionRepository::class);

        try {
            $dnsSubscription = $repository->getNotAdministrativelyEndedOrSuspendedDnsSubscription($this->domain);
        } catch (ModelNotFoundException) {
            return false;
        }

        if ($dnsSubscription->dnsDeployment === null) {
            return false;
        }

        return $dnsSubscription->dnsDeployment->nameserver_type === NameserverType::EXTERNAL;
    }
}
