<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\DTO;

use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;

class AuditLoggableIdentity
{
    public const SYSTEM = 'system';
    public const ADMIN = 'sw-admin';
    public const SUPPORT = 'support';
    public const CUSTOMER = 'customer';
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly UuidInterface $uuid,
        public readonly ?string $email,
        public readonly ?string $schemaId
    ) {
    }

    public function getIdentityType(): string
    {
        if ($this->schemaId === SchemaId::CUSTOMER->value) {
            return self::CUSTOMER;
        }

        if ($this->schemaId === SchemaId::EMPLOYEE->value) {
            if ($this->email === null) {
                return self::UNKNOWN;
            }
            return str_contains($this->email, '@sandwave') ? self::ADMIN : self::SUPPORT;
        }

        if ($this->schemaId === SchemaId::SYSTEM->value) {
            return self::SYSTEM;
        }

        return self::UNKNOWN;
    }
}
