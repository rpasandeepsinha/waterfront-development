<?php

declare(strict_types=1);

namespace Waterfront\Infra\Validation;

use Illuminate\Validation\Rules\Email;
use Waterfront\Support\Enums\Environment;

class EmailValidatorFactory
{
    public function __construct(
        private readonly Environment $environment,
    ) {
    }

    public function getValidator(): Email
    {
        $validator = new Email();
        $validator->strict();
        if (! in_array($this->environment, [Environment::DEV, Environment::TST], true)) {
            $validator->validateMxRecord();
        }

        return $validator;
    }
}
