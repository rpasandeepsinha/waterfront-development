<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Rules;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;

class ShouldHaveOneExistingCreateTag implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(
        private readonly ProvisionType $provisionType,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $provisioningRequestRepository = Container::getInstance()->make(ProvisioningRequestRepository::class);

        if (! is_string($value)) {
            $fail('The tag must be a valid UUID string.');
            return;
        }

        try {
            $tagUuid = Uuid::fromString($value);
        } catch (InvalidArgumentException) {
            $fail('The tag must be a valid UUID string.');
            return;
        }

        $count = $provisioningRequestRepository->createRequestCount($tagUuid, $this->provisionType);

        if ($count > 1) {
            $fail(sprintf(
                'The tag has multiple create requests linked for [%s] type.',
                $this->provisionType->value
            ));

            return;
        }

        if ($count < 1) {
            $fail(sprintf(
                'No create request with this tag in the [%s] type.',
                $this->provisionType->value
            ));
        }
    }
}
