<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Providers\ProviderRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaChangeHostingProviderAction extends NovaSubscriptionAction
{
    public $onlyOnDetail = true;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProviderRepository $providerRepository,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool => (
                $this->onlyForSingleSubscription($request)
                && $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::HOSTING)
            ),
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.change_hosting_provider');
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $subscription = $models->firstOrFail();

        if (! $subscription->hostingDeployment instanceof HostingDeployment) {
            return self::danger($this->translator->translate(
                'nova-action.error.only_hosting_subscriptions_supported_providers',
            ));
        }

        $newProvider = $this->providerRepository->getByType(
            ProviderType::HOSTING,
            ProviderSlug::from($fields->provider),
        );

        $hostingDeployment = $subscription->hostingDeployment;

        $hostingDeployment->provider_id = $newProvider->id;
        $hostingDeployment->save();

        return Action::message($this->translator->translate('nova-action.succes.hosting_provider_changed'));
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        $hostingProviders = [];

        foreach (Provider::where(['type' => ProviderType::HOSTING, 'enabled' => true])->get() as $provider) {
            /** @var Provider $provider */
            $hostingProviders[$provider->slug->value] = $provider->slug->value;
        }

        return [
            Select::make('provider')->options($hostingProviders),
        ];
    }
}
