<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Parameters;

use Webmozart\Assert\Assert;

readonly class ResellerHostingParameters
{
    public function __construct(
        public ?string $contactPerson,
        public string $username,
        public string $password,
        public string $email,
        public ?string $domain,
        public ?string $ipv4Address,
        public ?string $ipv6Address,
        public ?string $packageName,
        public ?int $resellerHostingId,
        public int $providerId
    ) {
        Assert::stringNotEmpty($username);
        Assert::stringNotEmpty($password);
        Assert::stringNotEmpty($email);

        Assert::nullOrStringNotEmpty($contactPerson);
        Assert::nullOrStringNotEmpty($domain);
        Assert::nullOrStringNotEmpty($ipv4Address);
        Assert::nullOrStringNotEmpty($ipv6Address);
        Assert::nullOrStringNotEmpty($packageName);
    }
}
