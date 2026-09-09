<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\Helpers;

use InvalidArgumentException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;

class PermissionsHelper
{
    public static function getKratosSchemaId(AuthenticatedEmployee|AuthenticatedCustomer|AuthenticatedSystem|AuthenticatedUnregisteredCustomer $authenticatedSubject): SchemaId
    {
        return match ($authenticatedSubject::class) {
            AuthenticatedEmployee::class => SchemaId::EMPLOYEE,
            AuthenticatedSystem::class => SchemaId::SYSTEM,
            AuthenticatedCustomer::class,
            AuthenticatedUnregisteredCustomer::class => SchemaId::CUSTOMER,
            default => throw new InvalidArgumentException('Unknown schema id'),
        };
    }
}
