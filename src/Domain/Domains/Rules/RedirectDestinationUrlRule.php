<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Rules;

use Pdp\SyntaxError;
use Pdp\UnableToResolveDomain;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class RedirectDestinationUrlRule extends AbstractValidator
{
    public function __construct(
        private readonly PublicSuffixList $rules,
        private readonly TranslatorInterface $translator
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value) || is_null($value));

        $host = $value === null ? null : $this->rules->getHostFromUrlOrDomain($value);

        if ($host === null) {
            return false;
        }

        if (! ((bool) filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
            return false;
        }

        try {
            $domain = $this->rules->getRules()->getICANNDomain($host);
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
