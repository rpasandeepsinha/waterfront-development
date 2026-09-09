<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Metrics;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaTotalCustomersMetric extends Value
{
    public $icon = '';

    public function __construct(
        private readonly TranslatorInterface $translator,
        string|null $component = null,
    ) {
        parent::__construct($component);
    }

    public function calculate(NovaRequest $request): ValueResult
    {
        $customerCount = Customer::query()
            ->whereNull('anonymized_at')
            ->count();

        $migratedCustomerCount = MigratedCustomer::query()
            ->whereHas('customers', function (Builder $query) {
                $query->whereNull('anonymized_at');
            })
            ->count();

        return $this->result($customerCount)
            ->format('0')
            ->suffix($this->translator->translate('nova_dashboard.metrics.from_migrations', ['count' => $migratedCustomerCount]));
    }

    public function name(): string
    {
        return $this->translator->translate('customer.plural');
    }
}
