<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\DNS\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaAssignVanityNsAction extends NovaSubscriptionAction
{
    public $sole = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly DnsVanityNameserverAssigner $dnsVanityNameserverAssigner,
        private readonly EventSubscriptionDataBuilder $dataBuilder,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
            $this->onlyForSingleSubscription($request)
                && $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::DNS)
        );

        $defaultConfirmText = $this->confirmText;
        $this->confirmText(sprintf(
            '%s %s',
            $this->translator->translate('nova-action.confirm.assign_vanity_ns'),
            $defaultConfirmText,
        ));
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.assign_vanity_ns');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();

        if (! $this->dnsProductSpecRepository->isPremiumDns($subscription->product)) {
            return Action::message($this->translator->translate('nova-action.error.not_premium_dns'));
        }

        if (! $subscription->dnsDeployment()->exists()) {
            $this->dataBuilder->buildDnsDeployment($subscription);
        }

        $dnsDeployment = $subscription->dnsDeployment;

        if ($dnsDeployment === null) {
            return Action::message($this->translator->translate('nova-action.error.no_dns_deployment'));
        }

        $dnsDeployment->vanityNameservers()->delete();
        $this->dnsVanityNameserverAssigner->assign($dnsDeployment);

        return Action::message($this->translator->translate('nova-action.success.assign_vanity_ns'));
    }
}
