<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\VPS\Resources;

use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\General\Traits\ViewOnlyResourceTrait;
use Waterfront\Apps\Nova\Subscriptions\Resources\NovaSubscriptionResource;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;

/**
 * @property VirtualMachineDeployment $resource
 */
class NovaVirtualMachineDeploymentResource extends Resource
{
    use ViewOnlyResourceTrait;

    public static string $model = VirtualMachineDeployment::class;

    public static $globallySearchable = false;

    public static string $orderBy = 'created_at';

    public static $perPageViaRelationship = 10;

    public static function getTranslationKey(): string
    {
        return 'virtual_machine_deployment';
    }

    public function title(): string
    {
        return self::translate('virtual_machine_deployment.singular');
    }

    /**
     * @return Field[]
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(
                self::translate('nova-resource-labels.subscription'),
                'subscription',
                NovaSubscriptionResource::class,
            )
                ->display(fn ($subscription) => $subscription instanceof NovaSubscriptionResource
                    ? $subscription->resource->product->name
                    : 'No subscription')
                ->exceptOnForms(),

            Text::make(self::translate('nova-resource-labels.cloudstack-id'), 'cloudstack_id')
                ->copyable()
                ->onlyOnDetail(),

            Text::make(self::translate('nova-resource-labels.customer-vps-name'), 'custom_name'),

            Text::make(
                self::translate('nova-resource-labels.manager-domain-account'),
                fn (VirtualMachineDeployment $deployment) => $deployment->managerDomainDeployment->account,
            )
                ->copyable()
                ->onlyOnDetail(),

            Text::make(
                self::translate('cloudstack-environments.singular'),
                fn (VirtualMachineDeployment $deployment) => $deployment->managerDomainDeployment->environment->name,
            )->onlyOnDetail(),

            DateTime::make(self::translate('nova-resource-labels.created_at'))->onlyOnIndex()->sortable(),

            Code::make(
                self::translate('subscription.vm-deployment.last_result'),
                'last_result',
            )->exceptOnForms(),

            DateTime::make(
                self::translate('subscription.vm-deployment.last_result_received'),
                'last_result_received',
            )->exceptOnForms(),

            BelongsToMany::make(
                self::translate('subscription.vm-deployment.ssh_keys'),
                'sshKeys',
                NovaSshKeyResource::class,
            )->onlyOnDetail(),
        ];
    }
}
