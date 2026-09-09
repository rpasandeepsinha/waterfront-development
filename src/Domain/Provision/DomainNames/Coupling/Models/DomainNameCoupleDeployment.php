<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Models;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Support\Database\UuidCast;

/**
 * @property string        $domain
 * @property ProvisionType $couple_type
 * @property UuidInterface $deployment_uuid
 */
class DomainNameCoupleDeployment extends ProvisionDeployment
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'deployment_uuid' => UuidCast::class,
            'couple_type' => ProvisionType::class,
        ]);
    }
}
