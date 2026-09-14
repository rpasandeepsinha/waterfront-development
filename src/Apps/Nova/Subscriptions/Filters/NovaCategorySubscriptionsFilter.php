<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionCategory;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaCategorySubscriptionsFilter extends Filter
{
    /** @var string */
    public $component = 'select-filter';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('subscriptions.subscriptions-categories');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        assert(is_string($value));
        $enum = SubscriptionCategory::from($value);
        $query->whereHas('category', function (Builder $query) use ($enum) {
            $query->where('name', $enum);
        });

        return $query;
    }

    /** @return array<string, string> */
    public function options(NovaRequest $request): array
    {
        $options = [];
        foreach (SubscriptionCategory::cases() as $name) {
            $options[$name->value] = $name->value;
        }

        return $options;
    }
}
