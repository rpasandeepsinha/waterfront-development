<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Rules;

use Pdp\SyntaxError;
use Pdp\UnableToResolveDomain;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class DomainNameRule extends AbstractValidator
{
    public function __construct(
        private readonly PublicSuffixList $rules,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value) || is_null($value));

        if (! (bool) filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }

        try {
            $domain = $this->rules->getRules()->getICANNDomain($value);
        } catch (UnableToResolveDomain|SyntaxError) {
            return false;
        }

        return $domain->registrableDomain()->value() !== null;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.domain_name');
    }
}
