<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Services\Invoice;

use Illuminate\Support\Facades\Log;
use Stringable;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * Provides shorthand methods for quickly modifying (multiple) Invoices (at once).
 */
class InvoiceModifier
{
    /**
     * Modifies each given Invoice with the given value(s).
     * Useful for when more than 1 Invoice needs to be updated with the same values.
     *
     * @param Invoice[]            $invoices
     * @param array<string, mixed> $values   Associative array with the key being the field to modify for each entity.
     */
    public function bulkUpdate(array $invoices, array $values): void
    {
        /** @var int[] $invoiceIds */
        $invoiceIds = [];

        foreach ($invoices as $invoice) {
            $invoiceIds[] = $invoice->id;

            $invoice->update($values);
        }

        Log::info(sprintf(
            'Completed bulk update action for these Invoice IDs: [%s], using these values: [%s].',
            implode(', ', $invoiceIds),
            implode(', ', array_map(
                fn (mixed $value, string $key): string => $key . '=' . $this->formatLogValue($value),
                $values,
                array_keys($values),
            ))
        ));
    }

    private function formatLogValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return strval($value);
        }

        return strval(json_encode($value));
    }
}
