<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CustomerContact;

use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Infra\Validation\EmailValidatorFactory;

/**
 * @property string $email
 */
class UpdateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $emailValidator = Container::getInstance()->make(EmailValidatorFactory::class)->getValidator();

        return [
            'email' => ['required', 'not_regex:[&]', $emailValidator],
        ];
    }
}
