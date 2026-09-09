<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Parameters;

use InvalidArgumentException;
use Webmozart\Assert\Assert;

class AppResellerHostingDomainCoupleParameters
{
    public function __construct(
        private readonly string $domain,
        private readonly string $uuid,
        private readonly string $username,
    ) {
        Assert::stringNotEmpty($this->domain);
        Assert::stringNotEmpty($this->uuid);
        Assert::stringNotEmpty($this->username);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * @param mixed[] $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: $data['domain'],
            uuid: $data['uuid'],
            username: $data['reseller_sub_username'],
        );
    }

    public function getUserName(): string
    {
        return $this->username;
    }
}
