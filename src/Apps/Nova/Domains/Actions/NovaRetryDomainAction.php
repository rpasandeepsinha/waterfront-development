<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaRetryDomainAction extends NovaSubscriptionAction
{
    public $sole = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $eventDispatcher,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly LoggerInterface $logger,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSingleSubscription($request)
                && $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
        );
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.retry_domain');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();

        if (is_null($subscription->domainDeployment)) {
            $provider = Provider::where('default', true)->where('type', ProviderType::DOMAIN)->firstOrFail();
            DomainDeployment::create([
                'subscription_uuid' => $subscription->uuid,
                'provider_id' => $provider->id,
            ]);
            $subscription->refresh();
        }

        Assert::isInstanceOf($subscription->domainDeployment, DomainDeployment::class, 'Only Domain Subscriptions allowed');

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            return self::danger($this->translator->translate('nova-action.error.subscription_invalid_for_retry'));
        }

        /** @var bool $privateWhois */
        $privateWhois = Arr::get($fields, 'private_whois', false);

        /** @var string|null $transferSecret */
        $transferSecret = Arr::get($fields, 'transfer_secret');

        /** @var bool $enableDnssec */
        $enableDnssec = Arr::get($fields, 'enable_dnssec', false);

        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $domainDeployment = $subscription->domainDeployment;
        $domainDeployment->update([
            'dnssec_enabled' => $enableDnssec,
            'transfer_secret' => $transferSecret,
            'private_whois_enabled' => $privateWhois,
        ]);

        $dnsSubscription = $this->domainDeploymentRepository->getDnsChildSubscription($subscription);
        if ($dnsSubscription === null) {
            $this->logger->notice(
                'Nova Retry Domain - Failed because DNS subscription could not be found for domain [{domain.name}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ]
            );

            return self::danger($this->translator->translate('nova-action.error.retry-domain-failed-dns'));
        }

        $this->eventDispatcher->dispatch(
            new CreateDns(
                $dnsSubscription->uuid,
                $domain,
            )
        );

        $this->eventDispatcher->dispatch(
            new CreateDomain(
                domain: $domain,
                subscription: $subscription,
                domainDeployment: $domainDeployment,
            )
        );

        return Action::message($this->translator->translate('nova-action.success.retried_domain'));
    }

    /**
     * @return array<int, NovaBoolField|Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make($this->translator->translate('nova-action.private_whois'), 'private_whois'),
            Text::make($this->translator->translate('nova-action.transfer_secret'), 'transfer_secret')
                ->nullable()
                ->rules('sometimes', 'nullable'),
            NovaBoolField::make($this->translator->translate('nova-action.enable_dns'), 'enable_dnssec'),
        ];
    }
}
