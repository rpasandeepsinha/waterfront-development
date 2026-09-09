<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Rules;

use Pdp\SyntaxError;
use Pdp\UnableToResolveDomain;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class RedirectFromUrlRule extends AbstractValidator
{
    private const string SCHEME_PATTERN = '#^[a-zA-Z][a-zA-Z0-9+\-.]*://#';
    private const string RAW_WHITESPACE_OR_CONTROL_CHARACTERS = '/[\x00-\x20\x7F]/';
    private const string MALFORMED_PERCENT_ENCODING = '/%(?![A-Fa-f0-9]{2})/';
    private const string MALFORMED_PATH_PERCENT_ENCODING = '/%(?![A-Fa-f0-9]{2}|\*)/';
    private const string ARRAY_STYLE_QUERY_PARAMETER_SUFFIX = '/\[(?:\d*)]$/';

    public function __construct(
        private readonly PublicSuffixList $rules,
        private readonly TranslatorInterface $translator
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        if ($this->hasScheme($value)) {
            return false;
        }

        $parsedUrl = parse_url('https://' . $value);
        if ($parsedUrl === false) {
            return false;
        }

        if ($this->hasUnsupportedUrlParts($parsedUrl)) {
            return false;
        }

        $path = $parsedUrl['path'] ?? '';
        if (! $this->hasValidPath($path)) {
            return false;
        }

        $query = $parsedUrl['query'] ?? null;
        if (! $this->hasValidQuery($query)) {
            return false;
        }

        return $this->hasValidRegistrableHost($value);
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.domain_name');
    }

    private function hasScheme(string $value): bool
    {
        return preg_match(self::SCHEME_PATTERN, $value) === 1;
    }

    /**
     * @param array<string, int|string> $parsedUrl
     */
    private function hasUnsupportedUrlParts(array $parsedUrl): bool
    {
        return array_key_exists('user', $parsedUrl)
            || array_key_exists('pass', $parsedUrl)
            || array_key_exists('port', $parsedUrl)
            || array_key_exists('fragment', $parsedUrl);
    }

    private function hasValidPath(string $path): bool
    {
        return ! $this->containsRawWhitespaceOrControlCharacter($path)
            && ! $this->containsMalformedPercentEncoding($path, allowCaddyWildcard: true);
    }

    private function hasValidQuery(?string $query): bool
    {
        if ($query === null) {
            return true;
        }

        if ($this->containsRawWhitespaceOrControlCharacter($query)
            || $this->containsMalformedPercentEncoding($query)
            || str_contains($query, ';')
        ) {
            return false;
        }

        return $this->hasValidQueryParameterNames($query);
    }

    private function hasValidQueryParameterNames(string $query): bool
    {
        foreach (explode('&', $query) as $parameter) {
            if ($parameter === '') {
                continue;
            }

            [$rawName] = explode('=', $parameter, 2);

            if ($this->normalizeQueryParameterName(urldecode($rawName)) === '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeQueryParameterName(string $name): string
    {
        return preg_replace(self::ARRAY_STYLE_QUERY_PARAMETER_SUFFIX, '', $name) ?? $name;
    }

    private function containsRawWhitespaceOrControlCharacter(string $value): bool
    {
        return preg_match(self::RAW_WHITESPACE_OR_CONTROL_CHARACTERS, $value) === 1;
    }

    private function containsMalformedPercentEncoding(string $value, bool $allowCaddyWildcard = false): bool
    {
        $pattern = $allowCaddyWildcard
            ? self::MALFORMED_PATH_PERCENT_ENCODING
            : self::MALFORMED_PERCENT_ENCODING;

        return preg_match($pattern, $value) === 1;
    }

    private function hasValidRegistrableHost(string $value): bool
    {
        $host = $this->rules->getHostFromUrlOrDomain($value);

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
}
