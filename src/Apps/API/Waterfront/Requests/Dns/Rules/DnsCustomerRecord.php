<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns\Rules;

use Illuminate\Support\Facades\Validator;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Infra\Validation\AbstractValidator;

class DnsCustomerRecord extends AbstractValidator
{
    /**
     * @var array<string, string[]>
     */
    private array $errors = [];

    public function __construct(private readonly DnsRecordsValidationService $dnsRecordsValidationService)
    {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        assert(is_array($value));

        $rules = $this->dnsRecordsValidationService->getRecordRules($value['type'], []);

        $validator = Validator::make($value, $rules);
        $validator->passes();
        $this->errors = $validator->errors()->toArray();

        return $validator->passes();
    }

    protected function message(): string
    {
        $errors = array_map(
            fn (array $error, string $attribute) => sprintf('%s: %s', strtoupper($attribute), implode(' ', $error)),
            $this->errors,
            array_keys($this->errors)
        );

        return implode(' ', $errors);
    }
}
