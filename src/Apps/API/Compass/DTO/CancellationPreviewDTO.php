<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\DTO;

use Carbon\CarbonImmutable;
use Waterfront\Domain\Harbor\DTO\Services\Invoice\InvoiceCrediter\InvoiceToCredit;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Subscriptions\Models\Subscription;

readonly class CancellationPreviewDTO
{
    /**
     * @param Subscription[]           $subscriptions
     * @param CancellationProblemDTO[] $blockingProblems
     * @param Invoice[]                $creditableInvoiceLines
     * @param InvoiceToCredit[]        $creditInvoiceLines
     */
    public function __construct(
        public array $subscriptions,
        public array $blockingProblems,
        public bool $creditAllowed,
        public bool $creditApplied,
        public CarbonImmutable $maxSelectableEndDate,
        public array $creditableInvoiceLines,
        public array $creditInvoiceLines,
    ) {
    }
}
