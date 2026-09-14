<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Subscriptions\Actions\NovaSubscriptionAction;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionAdministrativeStatusSelectField;
use Waterfront\Apps\Nova\Subscriptions\Fields\NovaSubscriptionTechnicalStatusSelectField;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaMicrosoft365BulkStatusUpdateAction extends NovaSubscriptionAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.microsoft365_bulk_status_update_action');
    }

    /**
     * @param Collection<int, Microsoft365Deployment> $subscriptions
     */
    public function handle(ActionFields $fields, Collection $subscriptions): ActionResponse|static
    {
        $microsoft365Deployment = $subscriptions->firstOrFail();
        $microsoft365CustomerInfo = $microsoft365Deployment->microsoft365CustomerInfo;

        if ($microsoft365CustomerInfo->mca_signed_at === null) {
            return self::danger($this->translator->translate('nova-action.failed.microsoft365-mca-not-signed'));
        }

        $fromAdministrativeStatus = $fields->get('from_administrative_status');
        assert(is_string($fromAdministrativeStatus));
        $toAdministrativeStatus = $fields->get('to_administrative_status');
        assert(is_string($toAdministrativeStatus));
        $fromTechnicalStatus = $fields->get('from_technical_status');
        assert(is_string($fromTechnicalStatus));
        $toTechnicalStatus = $fields->get('to_technical_status');
        assert(is_string($toTechnicalStatus));

        $childSubscriptions = $microsoft365Deployment->subscriptionChildren->where(
            'technical_status',
            $fromTechnicalStatus,
        )->where('administrative_status', $fromAdministrativeStatus);

        foreach ($childSubscriptions as $childSubscription) {
            $childSubscription->technical_status = $toTechnicalStatus;
            $childSubscription->administrative_status = $toAdministrativeStatus;
            $childSubscription->save();
        }

        return Action::message($this->translator->translate(
            'nova-action.microsoft365_bulk_status_update_action_completed',
        ));
    }

    /**
     * @return array<int, Select>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            NovaSubscriptionAdministrativeStatusSelectField::makeForEditing(
                'from_administrative_status',
                'nova-action.from_administrative_status',
            )
                ->displayUsingLabels()
                ->rules('required'),

            NovaSubscriptionAdministrativeStatusSelectField::makeForEditing(
                'to_administrative_status',
                'nova-action.to_administrative_status',
            )
                ->displayUsingLabels()
                ->rules('required'),

            NovaSubscriptionTechnicalStatusSelectField::make(
                'from_technical_status',
                'nova-action.from_technical_status',
            )
                ->displayUsingLabels()
                ->rules('required'),

            NovaSubscriptionTechnicalStatusSelectField::make('to_technical_status', 'nova-action.to_technical_status')
                ->displayUsingLabels()
                ->rules('required'),
        ];
    }
}
