<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Fields;

use Illuminate\Support\Arr;
use Laravel\Nova\Fields\Select;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionChangeTypeSelectField
{
    private const array OPTIONS = [
        ProductChangeType::UPGRADE->value => 'subscription-change.type.upgrade',
        ProductChangeType::DOWNGRADE->value => 'subscription-change.type.downgrade',
    ];

    public static function makeForEditing(): Select
    {
        $translator = resolve(TranslatorInterface::class);

        return Select::make($translator->translate('subscription-change.attributes.type'), 'type')
            ->options(Arr::map(self::OPTIONS, fn ($label) => $translator->translate($label)))
            ->onlyOnForms()
            ->required()
            ->rules('required');
    }
}
