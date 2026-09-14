<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Orders\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionAdministrativeStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionTechnicalStatusSelectField;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Services\SubscriptionService;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property OrderLineItem $resource
 */
class NovaProcessOrderLineItemAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SubscriptionService $subscriptionService,
        private readonly OneTimeServiceCreator $oneTimeServiceCreator,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.process_order_line_item');
    }

    /**
     * @param Collection<int, OrderLineItem> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var OrderLineItem $orderLineItem */
        $orderLineItem = $models->first();

        $order = $orderLineItem->order->firstOrFail();

        if ($orderLineItem->processed_at !== null) {
            return self::danger($this->translator->translate('nova-action.process_order_line_item.already_processed'));
        }

        if (in_array($order->status, [OrderStatus::ON_HOLD, OrderStatus::ABUSE], true)) {
            return self::danger($this->translator->translate('nova-action.process_order_line_item.invalid_status'));
        }

        if ($orderLineItem->product?->productGroup->slug === ProductGroupType::ONE_TIME_SERVICE) {
            $this->oneTimeServiceCreator->createFromOrderLineItem($orderLineItem);

            return self::message($this->translator->translate('nova-action.success.invoice_propagated_to_harbor'));
        }

        /** @var bool $manageSubscriptions */
        $manageSubscriptions = Arr::get($fields, 'manage_subscriptions', false);

        /** @var string $adminStatus */
        $adminStatus = Arr::get($fields, 'administrative_status', AdministrativeStatus::ACTIVE->value);

        /** @var string $technicalStatus */
        $technicalStatus = Arr::get($fields, 'technical_status', DomainStatus::ACTIVE->value);

        /** @var int|null $parentSubscriptionId */
        $parentSubscriptionId = Arr::get($fields, 'parent_subscription');

        $subscription = $this->subscriptionService->createSubscriptionFromOrderLineItem(
            $orderLineItem,
            $manageSubscriptions,
        );

        $orderLineItem->subscription()->associate($subscription);

        $subscription->administrative_status = $adminStatus;
        $subscription->technical_status = $technicalStatus;

        if ($parentSubscriptionId !== null) {
            $parent = Subscription::find($parentSubscriptionId);

            if (! $parent instanceof Subscription) {
                return self::danger($this->translator->translate('nova-action.error.parent_subscription_not_exists'));
            }

            $subscription->parent()->associate($parent);
        }

        $subscription->save();
        $orderLineItem->processed_at = CarbonImmutable::now();
        $orderLineItem->save();

        return self::message($this->translator->translate('nova-action.success.invoice_propagated_to_harbor'));
    }

    /**
     * @return array<int, NovaBoolField|Select|Number>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaBoolField::make(
                $this->translator->translate('nova-action.manage_subscriptions'),
                'manage_subscriptions',
            )->default(false),

            NovaSubscriptionAdministrativeStatusSelectField::makeForEditing()
                ->displayUsingLabels()
                ->required()
                ->default(AdministrativeStatus::ACTIVE->value),

            NovaSubscriptionTechnicalStatusSelectField::make()
                ->displayUsingLabels()
                ->required()
                ->default(TechnicalStatus::OK->value)
                ->help($this->translator->translate('subscription.info.technical_status')),

            Number::make(
                $this->translator->translate('nova-action.parent_subscription'),
                'parent_subscription',
            )->default(null),
        ];
    }
}
