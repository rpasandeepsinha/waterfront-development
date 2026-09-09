<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Rules;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class TenantNameRules extends AbstractValidator
{
    private string $message;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Customer $customer,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        // Customer has a tenant, but tenant name was given in json (error)
        if (
            $this->customer->microsoft365CustomerInfo()->exists()
            && is_string($value)
            && strlen($value) > 0
        ) {
            $this->message = 'validation.m365.customer-already-has-a-tenant';
            return false;
        }

        // Customer has a tenant and no tenant name was given in json (pass)
        if ($this->customer->microsoft365CustomerInfo()->exists()) {
            return true;
        }

        if (! is_string($value) || strlen($value) === 0) {
            $this->message = 'validation.m365.tenant-name-is-required';
            return false;
        }

        if (preg_match('([^a-zA-Z0-9])', $value) !== 0) {
            $this->message = 'validation.m365.tenant-name-alpha-num';
            return false;
        }

        if (strlen($value) > 27) {
            $this->message = 'validation.m365.tenant-name-max-length';
            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return $this->translator->translate($this->message);
    }
}
