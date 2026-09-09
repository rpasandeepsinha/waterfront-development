<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Migrations\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMigratedStateStatusFilter extends BooleanFilter
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-filter.migration_state.status_filter.title');
    }

    public function apply(NovaRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where(function (Builder $builder) use ($value): void {
            assert(is_array($value));

            if (Arr::get($value, 'placeholder') === true) {
                $builder->whereRelation('domainDeployment.provider', 'slug', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.provider', 'slug', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.mailProvider', 'slug', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.sitebuilderProvider', 'slug', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('sslDeployment.provider', 'slug', ProviderSlug::PLACEHOLDER->value);
            }

            if (Arr::get($value, 'not-placeholder') === true) {
                $builder->whereRelation('domainDeployment.provider', 'slug', '!=', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.provider', 'slug', '!=', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.mailProvider', 'slug', '!=', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('hostingDeployment.sitebuilderProvider', 'slug', '!=', ProviderSlug::PLACEHOLDER->value)
                    ->orWhereRelation('sslDeployment.provider', 'slug', '!=', ProviderSlug::PLACEHOLDER->value);
            }

            if (Arr::get($value, 'ok') === true) {
                $builder->whereIn('technical_status', [TechnicalStatus::OK->value, DomainStatus::ACTIVE]);
            }

            if (Arr::get($value, 'failed') === true) {
                $builder->whereIn('technical_status', [TechnicalStatus::FAILED->value, TechnicalStatus::PENDING->value, DomainStatus::FAILED]);
            }

            if (Arr::get($value, 'administratively-not-successful') === true) {
                $builder->whereRelation('customer.migratedCustomers', 'administrative_successful', false);
            }

            if (Arr::get($value, 'invoicing-not-enabled') === true) {
                $builder->whereRelation('customer.migratedCustomers', 'enable_invoicing', false);
            }

            if (Arr::get($value, 'migration-not-successful') === true) {
                $builder->whereRelation('customer.migratedCustomers', 'successful', false);
            }
        });
    }

    /**
     * @return array<string>
     */
    public function options(NovaRequest $request): array
    {
        return [
            $this->translator->translate('nova-filter.migration_state.status_filter.placeholder') => 'placeholder',
            $this->translator->translate('nova-filter.migration_state.status_filter.not_placeholder') => 'not-placeholder',
            $this->translator->translate('nova-filter.migration_state.status_filter.ok') => 'ok',
            $this->translator->translate('nova-filter.migration_state.status_filter.failed') => 'failed',
            $this->translator->translate('nova-filter.migration_state.status_filter.administratively-not-successful') => 'administratively-not-successful',
            $this->translator->translate('nova-filter.migration_state.status_filter.invoicing-not-enabled') => 'invoicing-not-enabled',
            $this->translator->translate('nova-filter.migration_state.status_filter.migration-not-successful') => 'migration-not-successful',
        ];
    }
}
