<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Apps\API\Waterfront\Policies\SubscriptionPolicy;
use Waterfront\Domain\Subscriptions\Actions\GetNextInvoicePriceAction;
use Waterfront\Domain\Subscriptions\Helpers\DetermineSubscriptionActiveStatusHelper;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Common\DateTimeFormat;

/**
 * @mixin Subscription
 *
 * @property Subscription $resource
 */
class SubscriptionResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        $type = $this->product->productGroup->slug;

        /** @var SubscriptionPolicy $subscriptionPolicy */
        $subscriptionPolicy = Container::getInstance()->make(SubscriptionPolicy::class);

        /** @var GetNextInvoicePriceAction $nextInvoicePriceAction */
        $nextInvoicePriceAction = Container::getInstance()->make(GetNextInvoicePriceAction::class);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'product_name' => $this->product->name,
            'product_slug' => $this->product->slug,
            'domain' => $this->domain,
            'type' => $type->value,
            'price' => $this->price,
            'contract_period' => $this->contract_period,
            'start_date' => $this->start_date->toW3cString(),
            'end_date' => $this->end_date->toW3cString(),
            'administrative_status' => $this->administrative_status,
            'technical_status' => $this->technical_status,
            'status' => DetermineSubscriptionActiveStatusHelper::resolve($this->resource),
            'available_actions' => $subscriptionPolicy->getAvailableActions($this->resource),
            'next_invoice' => [
                'date' => $this->resource->next_billing_date->format(DateTimeFormat::DATE),
                'price' => $nextInvoicePriceAction->execute($this->resource)->netPrice,
            ],
        ];
    }
}
