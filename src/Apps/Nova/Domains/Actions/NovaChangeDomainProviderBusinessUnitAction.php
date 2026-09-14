<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Domains\Resources\NovaDomainSubscriptionResource;
use Waterfront\Domain\Domains\Jobs\SetBusinessUnitOnDomainDeploymentsJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaChangeDomainProviderBusinessUnitAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $dispatcher,
    ) {
        $this->canSee(
            fn (NovaRequest $request) => $request->resource() === NovaDomainSubscriptionResource::class,
        );
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.change_domain_provider_business_unit');
    }

    /**
     * @param Collection<int, DomainDeployment> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var string|null $businessUnitId */
        $businessUnitId = $fields->get('domain_business_unit_id');
        $reset = $businessUnitId === null;

        if (! $reset && DomainProviderBusinessUnit::find($businessUnitId) === null) {
            return ActionResponse::danger($this->translator->translate('nova-action.error.no_business_unit_found'));
        }

        $modelIdChunks = $models->pluck('id')->chunk(20);

        foreach ($modelIdChunks as $domainDeploymentIdCollection) {
            /** @var int[] $domainDeploymentIds */
            $domainDeploymentIds = $domainDeploymentIdCollection->toArray();

            $this->dispatcher->dispatch(
                new SetBusinessUnitOnDomainDeploymentsJob(
                    businessUnitId: $reset ? null : intval($businessUnitId),
                    domainDeploymentIds: $domainDeploymentIds,
                ),
            );
        }

        return ActionResponse::message($this->translator->translate(
            'nova-action.success.domain_provider_business_unit_changed',
        ));
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make(
                name: $this->translator->translate('domain-business-unit.singular'),
                attribute: 'domain_business_unit_id',
            )->options(
                fn (): array => (
                    DomainProviderBusinessUnit::get()->pluck('name', 'id')->toArray()
                    + [null => $this->translator->translate('nova-action.select.reset_bu')]
                ),
            ),
        ];
    }
}
