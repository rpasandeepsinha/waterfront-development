<?php

declare(strict_types=1);

namespace Waterfront\Infra\HubspotClient\ValueObject;

use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

class EmailAddress implements NormalizableInterface
{
    public function __construct(
        public readonly string $emailAddress,
    ) {
        Assert::email($this->emailAddress);
    }

    public function __invoke(): string
    {
        return $this->emailAddress;
    }

    /**
     * @param mixed[] $context
     */
    public function normalize(NormalizerInterface $normalizer, ?string $format = null, array $context = []): string
    {
        return $this->emailAddress;
    }
}
