<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaAddDomainToSpamExpertsAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SpamExpertsClient $spamExpertsClient,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => (
                $this->onlyForSingleSubscription($request)
                && (
                    $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::HOSTING)
                    || $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
                )
            ),
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.add_domain_to_spam_experts');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->first();

        Assert::isInstanceOf($subscription, Subscription::class, 'Only Base Subscriptions allowed');

        $spamexpertsCluster = $subscription->hostingDeployment?->spamExpertsCluster;

        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        $this->spamExpertsClient->addDomain($domain, $spamexpertsCluster);

        return Action::message($this->translator->translate('spam_experts.action_success'));
    }
}
