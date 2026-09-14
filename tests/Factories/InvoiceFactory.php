<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use Waterfront\Domain\Invoices\Models\Invoice;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domainName = $this->faker->domainName();

        return [
            'start_date' => CarbonImmutable::now()->subYears(2)->toDateString(),
            'end_date' => CarbonImmutable::now()->subYears()->toDateString(),
            'period' => 12,
            'gross_price' => 444,
            'net_price' => 777,
            'vat_code' => 'NL21',
            'vat_rate' => 21,
            'ledger_code' => $this->faker->randomNumber(),
            'paid' => false,
            'credit_reason' => null,
            'title' => $domainName,
            'description' => 'fake_name invoice.description.for ' . $domainName,
            'group_label' => $domainName,
            'type' => InvoiceLine::TYPE_DEFAULT,
            'prepaid_reference' => null,
        ];
    }

    public function withCustomer(): self
    {
        return $this->for(new CustomerFactory());
    }

    public function sentToHarbor(): self
    {
        return $this->state(['sent_to_harbor_at' => CarbonImmutable::now()]);
    }
}
