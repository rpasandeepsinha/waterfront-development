<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaServerAllowNewWebsitesFilter extends Filter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('server.attributes.allow_new_websites');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        if ($value === 'yes') {
            return $query->where('allow_new_websites', true);
        } elseif ($value === 'no') {
            return $query->where('allow_new_websites', false);
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.hosting_servers.allow_new_websites.yes') => 'yes',
            $this->translator->translate('nova-filter.hosting_servers.allow_new_websites.no') => 'no',
        ];
    }
}
