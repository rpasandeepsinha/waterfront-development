<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Fields;

use Illuminate\Support\Arr;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionAdministrativeStatusSelectField
{
    private const array OPTIONS = [
        AdministrativeStatus::ACTIVE->value => 'subscription.administrative_statuses.active',
        AdministrativeStatus::CANCELED->value => 'subscription.administrative_statuses.canceled',
        AdministrativeStatus::ARCHIVED->value => 'subscription.administrative_statuses.archived',
        AdministrativeStatus::EXPIRED->value => 'subscription.administrative_statuses.expired',
        AdministrativeStatus::INACTIVE->value => 'subscription.administrative_statuses.inactive',
        AdministrativeStatus::SUSPENDED->value => 'subscription.administrative_statuses.suspended',
        AdministrativeStatus::ARCHIVING->value => 'subscription.administrative_statuses.archiving',
    ];

    public static function makeForEditing(
        string $name = 'administrative_status',
        string $translation_string = 'subscription.attributes.administrative_status',
    ): Select {
        $translator = resolve(TranslatorInterface::class);

        return Select::make($translator->translate($translation_string), $name)
            ->options(Arr::map(self::OPTIONS, fn ($label) => $translator->translate($label)))
            ->onlyOnForms();
    }

    public static function makeForDisplay(?string $termitionDate = null): Text
    {
        $translator = resolve(TranslatorInterface::class);

        return Text::make(
            $translator->translate('subscription.attributes.administrative_status'),
            'administrative_status',
            function ($value) use ($translator, $termitionDate) {
                $label = key_exists($value, self::OPTIONS) ? $translator->translate(self::OPTIONS[$value]) : $value;

                if ($value === AdministrativeStatus::SUSPENDED->value) {
                    return "<span style=\"color: red\">$label</span>";
                }

                if ($value === AdministrativeStatus::EXPIRED->value) {
                    $formatDate = ! is_null($termitionDate) ? "($termitionDate)" : '';

                    return "<span style=\"color: red\">$label $formatDate</span>";
                }

                return $label;
            },
        )
            ->asHtml()
            ->hideWhenCreating()
            ->hideWhenUpdating();
    }
}
