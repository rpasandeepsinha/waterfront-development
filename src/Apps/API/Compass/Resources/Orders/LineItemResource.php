<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Orders;

use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Apps\API\Compass\Policies\OrderPolicy;
use Waterfront\Apps\API\Compass\Resources\Products\ProductGroupResource;
use Waterfront\Domain\Orders\Models\OrderLineItem;

/** @property OrderLineItem $resource */
class LineItemResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        $orderPolicy = Container::getInstance()->make(OrderPolicy::class);

        return [
            'id' => $this->resource->id,
            'subscription_id' => $this->resource->subscription->id ?? null,
            'one_time_service_id' => $this->resource->one_time_service_id ?? null,
            'domain' => $this->resource->domain,
            'product_name' => $this->resource->product_name,
            'gross_price' => $this->resource->gross_price,
            'net_price' => $this->resource->net_price,
            'status' => $this->resource->status,
            'billing_period' => $this->resource->billing_period,
            'contract_period' => $this->resource->contract_period,
            'meta_data' => $this->resource->meta_data,
            'voucher_name' => $this->resource->voucherClaim?->voucher->display_name,
            'voucher_amount_claimed' => $this->resource->voucherClaim?->amount_claimed,
            'voucher_amount_type' => $this->resource->voucherClaim?->voucher->amount_type,
            'processed_at' => $this->resource->processed_at,
            'product_group' => $this->resource->product === null ? null : ProductGroupResource::make($this->resource->product->productGroup),
            'available_actions' => $orderPolicy->getAvailableCompassActionsForLineItem($this->resource),
        ];
    }
}
