<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaServerOwnerFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('server.attributes.owner');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where('owner', $value);
    }

    /**
     * @return array<mixed>
     */
    public function options(NovaRequest $request): array
    {
        return Server::query()->whereNotNull('owner')->distinct()->pluck('owner')->toArray();
    }
}
