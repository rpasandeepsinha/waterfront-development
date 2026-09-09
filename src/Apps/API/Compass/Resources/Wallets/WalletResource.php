<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources\Wallets;

use Illuminate\Http\Resources\Json\JsonResource;
use Waterfront\Domain\Customers\Models\CustomerWallet;

/** @property CustomerWallet $resource */
class WalletResource extends JsonResource
{
    /** @return array<mixed> */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'customer' => $this->resource->customer,
            'amount' => $this->resource->amount,
            'bankAccountNumber' => $this->resource->bank_account_number,
            'bankAccountName' => $this->resource->bank_account_name,
            'refundRequestedAt' => $this->resource->refund_requested_at,
            'csvDownloadedAt' => $this->resource->csv_downloaded_at,
        ];
    }
}
