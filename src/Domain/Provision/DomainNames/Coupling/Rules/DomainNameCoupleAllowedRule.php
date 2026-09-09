<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Rules;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Infra\Validation\AbstractValidator;
use Webmozart\Assert\Assert;

class DomainNameCoupleAllowedRule extends AbstractValidator
{
    private const array ALLOWED_TYPES = [
        ProvisionType::HOSTING,
        ProvisionType::RESELLER_HOSTING,
    ];

    public function __construct(
        private readonly ProvisioningRequestRepository $provisioningRequestRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        Assert::isInstanceOf($value, UuidInterface::class);

        $request = $this->provisioningRequestRepository->findByUuid($value);

        return in_array($request?->request_type, self::ALLOWED_TYPES, true);
    }

    protected function message(): string
    {
        return ProvisionErrorMessage::DOMAIN_NAME_COUPLE_NOT_ALLOWED->value;
    }
}
