<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Products\Repositories\DiscountRepository;
use Waterfront\Domain\Products\Repositories\ProductDiscountRepository;
use Waterfront\Domain\Products\VolumeDiscountService;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaAddVolumeDiscountAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DiscountRepository $discountRepository,
        private readonly ProductDiscountRepository $productDiscountRepository,
        private readonly VolumeDiscountService $volumeDiscountService,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.add_volume_discount');
    }

    /**
     * @return array<int, Select|Number>
     */
    public function fields(NovaRequest $request): array
    {
        $options = [];
        if ($request->resourceId !== null) {
            assert(is_string($request->resourceId));
            $productsWithVolumeDiscount = $this->productDiscountRepository->getAllUnassignedProductDiscountsWithVolumeDiscount((int) $request->resourceId);

            foreach ($productsWithVolumeDiscount as $product) {
                $options[$product->id] = $product->name;
            }
        }

        return [
            Select::make(
                $this->translator->translate('nova-action.volume_discount.discount_field'),
                'product_discount_id',
            )
                ->options($options)
                ->required()
                ->rules('required'),
            Number::make($this->translator->translate('subscription.attributes.period'), 'period')
                ->help($this->translator->translate('nova-action.volume_discount.period_help'))
                ->required()
                ->rules('required'),
        ];
    }

    /**
     * @param Collection<int, Customer> $customers
     */
    public function handle(ActionFields $fields, Collection $customers): ActionResponse|static
    {
        if (! ($fields->get('product_discount_id') > 0)) {
            self::danger(
                $this->translator->translate('nova-action.error.linking_volume_discount.title'),
                $this->translator->translate('nova-action.error.linking_volume_discount.description'),
            );
        }

        if ($customers->count() > 1) {
            self::danger(
                $this->translator->translate('nova-action.error.too_many_customers.title'),
                $this->translator->translate('nova-action.error.too_many_customers.description'),
            );
        }

        $customer = $customers->first();
        assert($customer instanceof Customer);

        if ($this->discountRepository->hasProductDiscounts($customer)) {
            self::danger(
                $this->translator->translate('nova-action.error.customer_has_volume_discounts.title'),
                $this->translator->translate('nova-action.error.customer_has_volume_discounts.description'),
            );
        }

        assert(is_numeric($fields->get('product_discount_id')));
        $productDiscountId = (int) $fields->get('product_discount_id');

        $productDiscount = ProductDiscount::where('id', $productDiscountId)->firstOrFail();

        if (! $productDiscount->product->exists) {
            self::danger(
                $this->translator->translate('nova-action.error.discounts_has_no_product_linked.title'),
                $this->translator->translate('nova-action.error.discounts_has_no_product_linked.description'),
            );
        }

        assert(is_numeric($fields->get('period')));

        $this->volumeDiscountService->attach(
            $customer,
            $productDiscount,
            $productDiscount->product,
            (int) $fields->get('period'),
        );

        return self::message($this->translator->translate('nova-action.success.volume_discount_linked'));
    }
}
