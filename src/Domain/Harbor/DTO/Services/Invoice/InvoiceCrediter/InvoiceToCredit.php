<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Waterfront\Domain\Invoices\Models\Invoice;

class InvoiceToCredit
{
    private int $amountToCredit;

    /**
     * @param ?int $amountToCredit Credits the full invoice by default.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly Invoice $invoice,
        ?int $amountToCredit = null,
        private readonly bool $shouldCreateNewInvoice = false,
        private readonly ?CarbonImmutable $creditStartDate = null,
        private readonly ?InvoiceLineCreditReason $creditReason = null,
    ) {
        $this->validateAndSetAmountToCredit($amountToCredit);
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getAmountToCredit(): int
    {
        return $this->amountToCredit;
    }

    public function shouldCreateNewInvoice(): bool
    {
        return $this->shouldCreateNewInvoice;
    }

    public function getCreditStartDate(): CarbonImmutable
    {
        return $this->creditStartDate ?? $this->invoice->start_date;
    }

    public function getCreditReason(): ?InvoiceLineCreditReason
    {
        return $this->creditReason;
    }

    private function validateAndSetAmountToCredit(?int $amountToCredit): void
    {
        // If no amount is given we take the invoice price.
        // And because of that the checks can be skipped.
        if ($amountToCredit === null) {
            $this->amountToCredit = $this->invoice->net_price;
            return;
        }

        // Amount must be same polarity as the invoice line.
        if (
            ($amountToCredit ^ $this->invoice->net_price) < 0
        ) {
            $exception = new InvalidArgumentException(sprintf(
                '%s with Invoice ID %d has opposite polarity set as amount to credit %d, invoice has amount %d',
                self::class,
                $this->invoice->id,
                $amountToCredit,
                $this->invoice->net_price
            ));

            Log::error($exception->getMessage());

            throw $exception;
        }

        // Amount to credit cannot be greater than the original invoice amount.
        // Because we already checked polarity to be the same, we can simply check with the absolute values.
        if (abs($amountToCredit) > abs($this->invoice->net_price)) {
            $exception = new InvalidArgumentException(sprintf(
                '%s with Invoice ID %d has %d set as amount to credit, but this amount exceeds that of the original Invoice\'s amount %d',
                self::class,
                $this->invoice->id,
                $amountToCredit,
                $this->invoice->net_price
            ));

            Log::error($exception->getMessage());

            throw $exception;
        }

        // We allow crediting invoices with 0 as net_price,
        // but not crediting 0 of an invoice that has a net_price > 0.
        if ($amountToCredit === 0 && $this->invoice->net_price > 0) {
            $exception = new InvalidArgumentException(sprintf(
                '%s with Invoice ID %d has %d set as amount to credit, but the amount to credit cannot be less than the original net_price (%d).',
                self::class,
                $this->invoice->id,
                $amountToCredit,
                $this->invoice->net_price,
            ));

            Log::error($exception->getMessage());

            throw $exception;
        }

        $this->amountToCredit = $amountToCredit;
    }
}
