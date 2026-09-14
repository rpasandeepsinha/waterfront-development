<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\OneTimeService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 *
 * @property array{subscriptionId: int, domain: ?string, title: string, price: int, amount: int} $resource
 */
class OneTimeServiceInvoiceLinePreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'domain' => $this->resource['domain'],
            'title' => $this->resource['title'],
            'price' => $this->resource['price'],
            'amount' => $this->resource['amount'],
        ];
    }
}
