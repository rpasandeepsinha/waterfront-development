<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaServerTypeFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('server.attributes.type');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('type', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return array_column(ServerType::cases(), 'value');
    }
}
