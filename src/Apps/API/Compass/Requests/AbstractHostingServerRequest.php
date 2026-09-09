<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Closure;
use Illuminate\Contracts\Translation\Translator as LaravelTranslator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Waterfront\Domain\Hosting\Rules\ValidServerRule;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

abstract class AbstractHostingServerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type'               => ['required', Rule::in(array_column(ServerType::cases(), 'value'))],
            'port'               => ['required', 'integer', 'max:65535'],
            'use_ssl'            => ['required', 'boolean'],
            'allow_new_websites' => ['required', 'boolean'],
            'name'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner'              => ['sometimes', 'nullable', 'string', 'max:255'],
            'ipv4'               => ['sometimes', 'nullable', 'ipv4'],
            'ipv6'               => ['sometimes', 'nullable', 'ipv6'],
            'username'           => ['sometimes', 'nullable', 'string', 'max:255'],
            'maximum_websites'   => ['sometimes', 'nullable', 'integer', 'min:0'],
            'password'           => ['sometimes', 'nullable', 'string'],
            'loginkey'           => ['sometimes', 'nullable', 'string'],
            'secret_key'         => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Ports the resource's afterValidation() hook: the server must be
     * reachable with the submitted connection details before it is stored.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $validServerRule = $this->container->make(ValidServerRule::class);
            $validServerRule->setData($this->serverAttributesForValidation());
            $validServerRule->validate('type', null, $this->failWithTranslatedMessage($validator));
        });
    }

    /**
     * @return array<string, mixed>
     */
    final protected function serverAttributesForValidation(): array
    {
        $attributes = $this->submittedServerAttributes();
        $attributes['domain'] = $attributes['hostname'] ?? null;

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function submittedServerAttributes(): array
    {
        return $this->only(new Server()->getFillable());
    }

    /**
     * @return Closure(string, string|null=): PotentiallyTranslatedString
     */
    private function failWithTranslatedMessage(Validator $validator): Closure
    {
        return function (string $message) use ($validator): PotentiallyTranslatedString {
            $translatedMessage = $this->container->make(TranslatorInterface::class)->translate($message);

            $validator->errors()->add('type', $translatedMessage);

            return new PotentiallyTranslatedString($translatedMessage, $this->container->make(LaravelTranslator::class));
        };
    }
}
