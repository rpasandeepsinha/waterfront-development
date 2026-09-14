<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Subscriptions\Fields;

use Laravel\Nova\Fields\Select;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaSubscriptionTechnicalStatusSelectField
{
    public static function make(
        string $name = 'technical_status',
        string $translation_string = 'subscription.attributes.technical_status',
    ): Select {
        $translator = resolve(TranslatorInterface::class);

        return Select::make($translator->translate($translation_string), $name)->options([
            TechnicalStatus::OK->value => $translator->translate('subscription.technical_statuses.ok'),
            TechnicalStatus::ERROR->value => $translator->translate('subscription.technical_statuses.error'),
            TechnicalStatus::FAILED->value => $translator->translate('subscription.technical_statuses.failed'),
            TechnicalStatus::REGISTRATION->value => $translator->translate(
                'subscription.technical_statuses.registration',
            ),
            TechnicalStatus::SUSPENDED->value => $translator->translate(
                'subscription.administrative_statuses.suspended',
            ),
            TechnicalStatus::DELETED->value => $translator->translate('subscription.technical_statuses.deleted'),
            TechnicalStatus::PENDING->value => $translator->translate('subscription.technical_statuses.pending'),
            DomainStatus::ACTIVE->value => $translator->translate('subscription.technical_statuses.extension_active'),
            TechnicalStatus::SUSPENSION_FAILED->value => $translator->translate(
                'subscription.technical_statuses.failed_suspension',
            ),
            TechnicalStatus::UNSUSPENSION_FAILED->value => $translator->translate(
                'subscription.technical_statuses.failed_unsuspension',
            ),
            TechnicalStatus::SUSPENDING->value => $translator->translate('subscription.technical_statuses.suspending'),
            TechnicalStatus::UNSUSPENDING->value => $translator->translate(
                'subscription.technical_statuses.unsuspending',
            ),
        ]);
    }
}
