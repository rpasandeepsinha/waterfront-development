<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Services;

use Pdp\ResolvedDomainName;
use RealtimeRegister\Domain\TLDInfo;

class RtrIdnLanguageCodeResolver
{
    private const string GERMAN_LANGUAGE_CODE = 'GER';
    private const string GERMAN_ALLOWED_CHARACTERS = '-0123456789abcdefghijklmnopqrstuvwxyzäöüß';

    public function resolve(ResolvedDomainName $domain, TLDInfo $tldInfo): ?string
    {
        $unicodeDomain = $domain->toUnicode();

        if ($unicodeDomain->domain()->isAscii()) {
            return null;
        }

        $languageCodes = $tldInfo->metadata->domainSyntax->languageCodes;

        if ($languageCodes === null) {
            return null;
        }

        $registeredLabel = $unicodeDomain->secondLevelDomain()->toString();
        $germanLanguageCode = $languageCodes->entities[self::GERMAN_LANGUAGE_CODE] ?? null;

        if (
            $germanLanguageCode !== null
            && $this->labelFitsAllowedCharacters(
                $registeredLabel,
                $germanLanguageCode->allowedCharacters ?? self::GERMAN_ALLOWED_CHARACTERS
            )
        ) {
            return self::GERMAN_LANGUAGE_CODE;
        }

        foreach ($languageCodes->entities as $languageCode => $language) {
            if (
                $language->allowedCharacters !== null
                && $this->labelFitsAllowedCharacters($registeredLabel, $language->allowedCharacters)
            ) {
                return $languageCode;
            }
        }

        return null;
    }

    private function labelFitsAllowedCharacters(string $registeredLabel, string $allowedCharacters): bool
    {
        $characters = preg_split('//u', mb_strtolower($registeredLabel), -1, PREG_SPLIT_NO_EMPTY);

        if ($characters === false) {
            return false;
        }

        $allowedCharacters = mb_strtolower($allowedCharacters);

        return array_all($characters, fn ($character) => str_contains($allowedCharacters, $character));
    }
}
