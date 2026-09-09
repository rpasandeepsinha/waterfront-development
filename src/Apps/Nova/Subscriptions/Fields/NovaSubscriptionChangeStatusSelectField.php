<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Fields;

use Illuminate\Support\Arr;
use Laravel\Nova\Fields\Select;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionChangeStatusSelectField
{
    private const array OPTIONS = [
        SubscriptionChangeStatus::REQUESTED->value => 'subscription-change.status.requested',
        SubscriptionChangeStatus::INPROGRESS->value => 'subscription-change.status.in_progress',
        SubscriptionChangeStatus::COMPLETED->value => 'subscription-change.status.completed',
    ];

    public static function makeForEditing(): Select
    {
        $translator = resolve(TranslatorInterface::class);

        return Select::make($translator->translate('subscription-change.attributes.status'), 'status')
            ->options(Arr::map(self::OPTIONS, fn ($label) => $translator->translate($label)))
            ->onlyOnForms()
            ->required()
            ->rules('required');
    }
}
