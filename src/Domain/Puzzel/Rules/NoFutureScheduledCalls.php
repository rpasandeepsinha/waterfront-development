<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackRequestRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\Translator;

class NoFutureScheduledCalls implements ValidationRule
{
    public function __construct(
        private readonly Translator $translator,
        private readonly PuzzelCallbackRequestRepository $callbackRequestRepository,
        private readonly AuthenticationManager $authManager
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $now = CarbonImmutable::now();
        $customer = $this->authManager->getAuthenticatedCustomer()->customer;
        if ($this->callbackRequestRepository->findFirstFutureForCustomer($customer, $now) !== null) {
            $fail($this->translator->translate('validation.puzzel-existing-request'));
        }
    }
}
