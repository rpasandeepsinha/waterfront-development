<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Provision;

use Laravel\Nova\Fields\Badge;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;

class StatusBadgeConverter
{
    public const string MISSING = 'missing';

    public static function createProvisionStatusBadge(string $name, string $attribute = 'status'): Badge
    {
        return Badge::make($name, $attribute)->map([
            ProvisionStatus::SUCCESS->value => 'success',
            ProvisionStatus::FAILED->value => 'danger',
            ProvisionStatus::VALIDATION_ERROR->value => 'danger',
            ProvisionStatus::RETRYING->value => 'info',
            ProvisionStatus::DELETED->value => 'success',
            ProvisionStatus::DELETING->value => 'warning',
            ProvisionStatus::DELETION_FAILED->value => 'danger',
            ProvisionStatus::PENDING->value => 'info',
            self::MISSING => 'warning',
        ])->withIcons();
    }
}
