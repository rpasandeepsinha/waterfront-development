<?php

declare(strict_types=1);

namespace Tests\Domain\DNS\Helpers;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsRecordValidator;

trait DnsRecordValidatorHelper
{
    /**
     * @param mixed[] $data
     *
     * @throws ValidationException
     */
    private function createDnsRecordValidator(array $data): DnsRecordValidator
    {
        $translator = self::resolve(Translator::class);
        $validationService = self::resolve(DnsRecordsValidationService::class);

        return new DnsRecordValidator($validationService, $translator, $data, [], []);
    }
}
