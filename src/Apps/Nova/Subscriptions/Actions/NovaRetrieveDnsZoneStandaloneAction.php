<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Infra\PowerDnsClient\Clients\RawPowerDnsRetriever;
use Waterfront\Infra\PowerDnsClient\Serializers\PowerDnsSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaRetrieveDnsZoneStandaloneAction extends NovaSubscriptionAction
{
    private readonly Serializer $serializer;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RawPowerDnsRetriever $rawPowerDnsRetriever,
        private readonly DnsService $dnsService,
    ) {
        $this->standalone();
        $this->onlyOnIndex();

        $this->serializer = PowerDnsSerializerFactory::getSerializer();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retrieve_dns_zone');
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Domain', 'domain')
                ->rules('required')
                ->required(),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $domain = $fields->get('domain');
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
