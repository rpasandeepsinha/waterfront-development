<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Waterfront\Domain\Customers\Models\CustomerWallet;

class CustomerWalletRepository
{
    /**
     * @return Collection<int,CustomerWallet>
     */
    public function findAll(): Collection
    {
        return CustomerWallet::query()->with('customer')->get();
    }

    /**
     * @param int[] $walletIds
     *
     * @return Collection<int, CustomerWallet>
     */
    public function getWalletsByIdsNotExported(array $walletIds): Collection
    {
        return CustomerWallet::whereIn('id', $walletIds)
            ->whereNotNull('refund_requested_at')
            ->whereNull('csv_downloaded_at')
            ->get();
    }

    /**
     * @param Collection<int, CustomerWallet> $collection
     */
    public function setCsvDownloadedAt(Collection $collection, DateTimeImmutable $date): void
    {
        CustomerWallet::query()
            ->whereIn('id', $collection->pluck('id')->all())
            ->update([
                'csv_downloaded_at' => $date,
            ]);
    }
}
