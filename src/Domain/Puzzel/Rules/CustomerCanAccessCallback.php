<?php

declare(strict_types=1);

namespace Waterfront\Domain\Puzzel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Waterfront\Domain\Subscriptions\Services\ServicePlanChecker;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Translation\Translator;

class CustomerCanAccessCallback implements ValidationRule
{
    public function __construct(
        private readonly Translator $translator,
        private readonly AuthenticationManager $authManager,
        private readonly ServicePlanChecker $servicePlanChecker
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $authSubject = $this->authManager->getAuthenticatedSubject();

        if (
            $authSubject instanceof AuthenticatedCustomer
            && $this->servicePlanChecker->hasAccessToServicePlan($authSubject->customer)
        ) {
            return;
        }

        $fail($this->translator->translate('validation.puzzel-no-callback-access'));
    }
}
