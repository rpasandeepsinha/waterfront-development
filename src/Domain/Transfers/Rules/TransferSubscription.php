<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Rules;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Transfers\Services\TransferService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class TransferSubscription extends AbstractValidator
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly TranslatorInterface $translator,
        private readonly Customer $from,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        return $this->transferService->validateSubscriptionFromUuid($value, $this->from);
    }

    protected function message(): string
    {
        return $this->translator->translate('transfer.customers.store.subscription_validation');
    }
}
