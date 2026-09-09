<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaRemoveDnsZoneAction extends NovaSubscriptionAction
{
    public $onlyOnDetail = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
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
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.remove_dns_zone');
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

        $this->dnsService->deleteZone($domain);

        return Action::message($this->translator->translate('nova-action.success.remove_dns_zone_succes'));
    }
}
