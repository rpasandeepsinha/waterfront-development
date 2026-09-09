<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication\DTO;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use Waterfront\Support\Exceptions\NotImplementedException;

class AuthenticatedEmployee implements Authenticatable, Authorizable
{
    use AuthorizableTrait;

    public function __construct(
        public KratosIdentity $identitySchema,
        public readonly bool $verified,
    ) {
    }

    public function getAuthIdentifierName()
    {
        return 'uuid';
    }

    public function getAuthIdentifier(): UuidInterface
    {
        return $this->identitySchema->id;
    }

    public function getAuthPassword()
    {
        throw new NotImplementedException('Employee doesn\'t use passwords');
    }

    public function getAuthPasswordName(): string
    {
        throw new NotImplementedException('Employee doesn\'t use passwords');
    }

    public function getRememberToken()
    {
        throw new NotImplementedException('Remember me is not implemented');
    }

    public function setRememberToken($value)
    {
        throw new NotImplementedException('Remember me is not implemented');
    }

    public function getRememberTokenName()
    {
        throw new NotImplementedException('Remember me is not implemented');
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return ['name' => $this->identitySchema->traits?->email];
    }

    public function getKey(): string
    {
        return $this->identitySchema->id->toString();
    }
}
