<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Validators;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;

/**
 * @property Closure|null $resolver
 */
class DnsValidatorFactory extends Factory
{
    /**
     * Resolve a new Validator instance.
     *
     *
     * @param mixed[] $data
     * @param mixed[] $rules
     * @param mixed[] $messages
     * @param mixed[] $customAttributes
     *
     * @throws ValidationException
     */
    protected function resolve(array $data, array $rules, array $messages, array $customAttributes): Validator|DnsRecordValidator
    {
        $validationService = Container::getInstance()->make(DnsRecordsValidationService::class);

        if (is_null($this->resolver)) {
            return new DnsRecordValidator(
                $validationService,
                $this->translator,
                $data,
                $rules,
                $messages,
                $customAttributes
            );
        }

        $validator = call_user_func(
            $this->resolver,
            $validationService,
            $this->translator,
            $data,
            $rules,
            $messages,
            $customAttributes
        );
        assert($validator instanceof Validator);

        return $validator;
    }
}
