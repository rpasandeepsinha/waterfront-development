<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductAddonCouplingRepository;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Jobs\CreateTrusteeSubscriptionJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaAddTrusteeAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $jobDispatcher,
        private readonly ProductRepository $productRepository,
        private readonly ProductAddonCouplingRepository $productAddonCouplingRepository,
    ) {
        $this->canSee(
            fn (NovaRequest $request): bool =>
                $this->onlyForSubscriptionsWithProductGroupType($request, ProductGroupType::EXTENSION)
                && $this->onlyForSingleSubscription($request)
        );

        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.add_trustee_subscription');
    }

    /**
     * @return array<int, Select|Number>
     */
    public function fields(NovaRequest $request): array
    {
        $options = [];
        $requestId = $request->selectedResourceIds();
        if ($requestId !== null && $requestId->isNotEmpty()) {
            assert(is_string($requestId->first()));

            $selectedSubscriptions = $this->getSelectedSubscriptionsFromRequest($request);
            $addonProducts = $this->productAddonCouplingRepository->getAddonProductsForParentProduct($selectedSubscriptions->firstOrFail()->product);

            foreach ($addonProducts as $product) {
                $options[$product->addonProduct->id] = $product->addonProduct->name;
            }
        }

        return [
            Select::make('addon product', 'addon_product_id')
                ->options($options)
                ->required()->rules('required'),
        ];
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        if (! ($fields->get('addon_product_id') > 0)) {
            return self::danger(
                $this->translator->translate('nova-action.error.no_product_selected'),
            );
        }

        $subscription = $models->first();
        assert($subscription instanceof Subscription);
        assert(is_numeric($fields->get('addon_product_id')));
        $addonProduct = $this->productRepository->findProductById((int) $fields->get('addon_product_id'));

        if (! $this->productAddonCouplingRepository->existsForParentIdAndAddonId($subscription->product->id, $addonProduct->id)) {
            return self::danger($this->translator->translate('nova-action.error.product_combo_not_supported', ['mainProduct' => $subscription->product->name, 'addonProduct' => $addonProduct->name]));
        }
        $this->jobDispatcher->dispatch(new CreateTrusteeSubscriptionJob($subscription, $addonProduct));

        return self::message($this->translator->translate('nova-action.success.subscription_created'));
    }
}
