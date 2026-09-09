<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaWalletRefundFilter extends Filter
{
    public const string FILTER_IS_REQUESTED = 'refund_requested_at';
    public const string FILTER_IS_CSV_DOWNLOADED = 'csv_downloaded_at';

    public function __construct(
        private readonly string $filterFor,
        private readonly TranslatorInterface $translator
    ) {
        Assert::oneOf($this->filterFor, [
            self::FILTER_IS_REQUESTED,
            self::FILTER_IS_CSV_DOWNLOADED,
        ]);
    }

    public function name(): string
    {
        return $this->translator->translate($this->key());
    }

    public function key(): string
    {
        return "nova-filter.customer.wallet.{$this->filterFor}";
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === 'true') {
            return $query->whereNotNull($this->filterFor);
        }

        return $query->whereNull($this->filterFor);
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate("nova-filter.customer.wallet.is_{$this->filterFor}") => 'true',
            $this->translator->translate("nova-filter.customer.wallet.is_not_{$this->filterFor}") => 'false',
        ];
    }
}
