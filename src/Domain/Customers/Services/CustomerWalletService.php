<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Waterfront\Apps\Nova\Customers\Services\CustomerWallet\CsvExporter;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Repositories\CustomerWalletRepository;

class CustomerWalletService
{
    public function __construct(
        private readonly CustomerWalletMailerService $mailerService,
        private readonly CsvExporter $csvExporter,
        private readonly CustomerWalletRepository $customerWalletRepository,
    ) {
    }

    public function requestRefund(CustomerWallet $wallet, string $name, string $number): void
    {
        if ($wallet->refund_requested_at !== null) {
            return;
        }

        $wallet->bank_account_name = $name;
        $wallet->bank_account_number = $number;
        $wallet->refund_requested_at = CarbonImmutable::now();
        $wallet->save();

        $this->mailerService->sendRequestConfirmation(
            $wallet,
            $name,
            $number,
        );
    }

    /**
     * @param Collection<int, CustomerWallet> $customerWallet
     */
    public function createRefundCsv(Collection $customerWallet): string
    {
        $now = CarbonImmutable::now();
        $exportedString = $this->csvExporter->export(
            [
                'customer_number',
                'bank_account_name',
                'bank_account_number',
                'amount',
            ],
            array_map(fn (CustomerWallet $wallet): array => [
                $wallet->customer->customer_number,
                $wallet->bank_account_name,
                $wallet->bank_account_number,
                $wallet->amount,
            ], $customerWallet->all()),
        );

        $this->customerWalletRepository->setCsvDownloadedAt($customerWallet, $now);

        return $exportedString;
    }
}
