<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;

/**
 * Validator for DNS records.
 */
class DnsRecordValidator extends Validator
{
    /**
     * Create a new DnsRecordValidator instance.
     *
     * @param mixed[] $data
     * @param mixed[] $rules
     * @param mixed[] $messages
     * @param mixed[] $customAttributes
     *
     * @throws ValidationException
     */
    public function __construct(
        private readonly DnsRecordsValidationService $validationService,
        Translator $translator,
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        parent::__construct($translator, $data, $rules, $messages, $customAttributes);
        $this->initialRules = $rules;
        $this->translator = $translator;
        $this->customMessages = $messages;
        $this->data = $this->parseData($data);
        $this->customAttributes = $customAttributes;

        if (! array_key_exists('type', $data)) {
            $this->setRules($rules);
            $this->addFailure('type', 'required');

            throw new ValidationException($this);
        }

        $rules += $this->getRecordRules($data['type']);

        $this->setRules($rules);
    }

    /**
     * Gets the rules for a certain record type.
     *
     * @return mixed[]
     */
    protected function getRecordRules(string $type): array
    {
        return $this->validationService->getRecordRules($type, $this->customAttributes);
    }
}
