<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class DomainIsUnclaimedRule extends AbstractValidator
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));

        return ! $this->subscriptionRepository->domainExistsInSubscription($value);
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.not_in_another_account');
    }
}
