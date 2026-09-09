<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneTimeServices\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaStatusFilter extends BooleanFilter
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.one-time-service.field.status');
    }

    /** @return array<string, bool> */
    public function default(): array
    {
        return [
            OneTimeServiceStatus::OPEN->value => true,
            OneTimeServiceStatus::IN_PROGRESS->value => true,
            OneTimeServiceStatus::DONE->value => false,
        ];
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        Assert::isArray($value);
        return $query->whereIn('status', array_keys(array_filter(
            $value,
            fn ($include) => $include,
        )));
    }

    /**
     * @return array<string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::OPEN->name)) => OneTimeServiceStatus::OPEN->value,
            $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::IN_PROGRESS->name)) => OneTimeServiceStatus::IN_PROGRESS->value,
            $this->translator->translate('one-time-service.status.' . strtolower(OneTimeServiceStatus::DONE->name)) => OneTimeServiceStatus::DONE->value,
        ];
    }
}
