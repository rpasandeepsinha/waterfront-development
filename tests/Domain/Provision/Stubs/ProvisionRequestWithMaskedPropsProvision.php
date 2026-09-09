<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Stubs;

use Ramsey\Uuid\UuidInterface;
use SensitiveParameter;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Hosting\Requests\HostingProvisionRequest;
use Waterfront\Domain\Provision\Interfaces\ProvisionContextRequestInterface;

class ProvisionRequestWithMaskedPropsProvision extends HostingProvisionRequest implements ProvisionContextRequestInterface
{
    public ProvisionRequestName $name = ProvisionRequestName::GET_HOSTING_SSO;

    /**
     * @param array<mixed> $secretArray
     */
    public function __construct(
        public readonly string $username,
        #[SensitiveParameter]
        public readonly string $password,
        #[SensitiveParameter]
        public readonly int $secretInt,
        #[SensitiveParameter]
        public readonly float $secretFloat,
        #[SensitiveParameter]
        public readonly array $secretArray,
        public protected(set) UuidInterface $context
    ) {
    }
}
