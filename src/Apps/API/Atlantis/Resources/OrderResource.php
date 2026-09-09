<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Orders\Models\OrderLineItem;

/** @mixin OrderLineItem */
class OrderResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<mixed>
     */
    public function toArray($request): array
    {
        $this->loadMissing('product', 'product.productGroup');

        return [
            'id'           => $this->id,
            'domain'       => $this->domain,
            'gross_price'  => $this->gross_price,
            'net_price'    => $this->net_price,
            'product_name' => $this->product_name,
            'contract_period' => $this->contract_period,
            'billing_period' => $this->billing_period,
            'status'       => $this->status,
            'type'         => $this->product?->productGroup?->slug,
        ];
    }
}
