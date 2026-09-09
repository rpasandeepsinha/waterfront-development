<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Orders;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Orders\Models\Order;

/** @property Order $resource */
class OrderResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'customer_number' => $this->resource->customer->customer_number,
            'uuid' => $this->resource->uuid,
            'total_price' => $this->resource->total_price,
            'payment_method' => $this->resource->payment_method,
            'payment_type' => $this->resource->customer->payment_type,
            'last_payment_status' => $this->resource->payments->last()?->status,
            'administration_fees' => $this->resource->administration_fees,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at?->toW3cString(),
            'updated_at' => $this->resource->updated_at?->toW3cString(),
            'ordered_by' => $this->orderedBy($this->resource),
            'line_items' => LineItemResource::collection($this->resource->lineItems),
        ];
    }

    /**
     * @return array<mixed>
     */
    private function orderedBy(Order $order): array
    {
        $orderedBy = [];

        if ($order->ordered_by_uuid !== null) {
            $orderedBy = ['ordered_by_uuid' => $this->resource->ordered_by_uuid];
        }

        if ($order->ordered_by_metadata !== null) {
            $orderedBy = [...$orderedBy, 'ordered_by_metadata' => json_decode($order->ordered_by_metadata, true)];
        }

        return $orderedBy;
    }
}
