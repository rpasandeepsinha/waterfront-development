<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Rules;

use Illuminate\Container\Container;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * Rule for checking whether the name is unique for a record.
 *
 * Example: An A/AAAA record and CNAME record for the same name can't exist.
 */
class NoDuplicate extends AbstractValidator
{
    /** @param string[] $typesToCheck */
    public function __construct(private readonly string $zoneName, private readonly array $typesToCheck)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_string($value));
        $dnsService = Container::getInstance()->make(DnsService::class);

        $records = $dnsService->getDnsRecordsForDomain($this->zoneName);

        $noDuplicate = true;

        foreach ($records as $record) {
            // check if something already exists for this name
            if (in_array($record->getType(), $this->typesToCheck, true) &&
                strcasecmp(rtrim($value, '.'), rtrim($record->getName(), '.')) === 0) {
                $noDuplicate = false;
                break;
            }
        }

        return $noDuplicate;
    }

    protected function message(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return $translator->translate('validation.record_name_unique');
    }
}
