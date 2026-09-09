<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Validators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;

class UpdateDnsRecordValidator extends Validator
{
    /**
     * @param mixed[] $data
     * @param mixed[] $rules
     * @param mixed[] $messages
     * @param mixed[] $customAttributes
     *
     * @throws ValidationException
     */
    public function __construct(
        DnsRecordsValidationService $validationService,
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

        $newType = Arr::get($data, 'new.type', '');
        if ($newType === '') {
            $this->setRules($rules);
            $this->addFailure('new.type', 'required');

            throw new ValidationException($this);
        }

        assert(is_array($data['new']));
        assert(is_string($data['new']['type']));
        $rules += $validationService->getRecordRules($data['new']['type'], $customAttributes);

        foreach ($rules as $key => $value) {
            if (! array_key_exists('new.' . $key, $rules)) {
                $rules['new.' . $key] = $value;
                unset($rules[$key]);
            }
        }

        $this->setRules($rules);
    }
}
