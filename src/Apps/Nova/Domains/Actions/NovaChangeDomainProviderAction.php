<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaChangeDomainProviderAction extends NovaSubscriptionAction
{
    public $onlyOnDetail = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProviderRepository $providerRepository
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSingleSubscription($request)
                && $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.change_domain_provider');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->first();
        Assert::isInstanceOf($subscription, Subscription::class, 'Only Base Subscriptions allowed');

        $newProvider = $this->providerRepository->getByType(ProviderType::DOMAIN, ProviderSlug::from($fields->provider));

        $domainDeployment = $subscription->domainDeployment;
        Assert::isInstanceOf($domainDeployment, DomainDeployment::class, 'Only allowed with a Domain subscription attached');

        $domainDeployment->provider_id = $newProvider->id;
        $domainDeployment->save();

        return Action::message($this->translator->translate('nova-action.success.domain_provider_changed'));
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        $domainProviders = [];

        foreach (Provider::where(['type' => ProviderType::DOMAIN, 'enabled' => true])->get() as $provider) {
            /** @var Provider $provider */
            $domainProviders[$provider->slug->value] = $provider->slug->value;
        }

        return [
            Select::make('provider')->options($domainProviders),
        ];
    }
}
