<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\PowerDnsClient\Clients\RawPowerDnsRetriever;
use Waterfront\Infra\PowerDnsClient\Serializers\PowerDnsSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaRetrieveDnsZoneAction extends NovaSubscriptionAction
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RawPowerDnsRetriever $rawPowerDnsRetriever,
        private readonly DnsService $dnsService,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSingleSubscription($request)
                && (
                    $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
                    ||
                    $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::DNS)
                )
        );

        $this->sole();

        $this->serializer = PowerDnsSerializerFactory::getSerializer();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retrieve_dns_zone');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();
        $domainDeployment = $subscription->domainDeployment;

        if (! $domainDeployment instanceof DomainDeployment && ! $subscription->product->isDnsProduct()) {
            throw new InvalidArgumentException('Only allowed with a domain or DNS subscription');
        }

        $domain = $subscription->domain;
        assert(is_string($domain));

        $zoneMetadata = $this->dnsService->getMetadata($domain);

        return self::modal('modal-response', [
            'title' => $this->translator->translate(
                'nova-action.retrieve_dns_zone_description',
                [
                    'version' => $this->rawPowerDnsRetriever->getPowerDnsVersion(),
                ]
            ),
            'code' => json_encode([
                'zone' => $this->rawPowerDnsRetriever->getPowerDnsZoneResponseBody($domain),
                'metadata' => $this->serializer->normalize($zoneMetadata),
            ], JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }
}
