<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Validation;

use Illuminate\Contracts\Validation\Validator;

class ValidationResult
{
    /**
     * @param array<string, string[]> $messages
     */
    public function __construct(
        public bool $isValid = true,
        public array $messages = [],
    ) {
    }

    public function addValidationError(string $valueName, string $message): void
    {
        $this->isValid = false;
        if (array_key_exists($valueName, $this->messages)) {
            $this->messages[$valueName][] = $message;

            return;
        }

        $this->messages[$valueName] = [$message];
    }

    /**
     * @param string[] $messages
     */
    public function addValidationErrors(string $valueName, array $messages): void
    {
        $this->isValid = false;
        $this->messages[$valueName] = $messages;
    }

    public static function fromValidator(Validator $validator): self
    {
        $validationResult = new self();
        foreach ($validator->errors()->messages() as $key => $messages) {
            $validationResult->addValidationErrors($key, $messages);
        }

        return $validationResult;
    }
}
