<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;
use Webmozart\Assert\Assert;

class NotAnonymousDomainContactRule extends AbstractValidator
{
    private string $message;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        Assert::integer($value);

        /** @var int $value */
        $domainContact = DomainContact::find($value);

        if (! $domainContact instanceof DomainContact) {
            $this->message = $this->translator->translate('validation.domain_contact.does_not_exist');
            return false;
        }

        if ($domainContact->has_anonymous_handle) {
            $this->message = $this->translator->translate('validation.domain_contact.anonymous.rule');
            return false;
        }
        return true;
    }

    protected function message(): string
    {
        return $this->message;
    }
}
