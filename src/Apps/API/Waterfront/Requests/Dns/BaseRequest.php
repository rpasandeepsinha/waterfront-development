<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\DNS\Entities\DnsRecordTypes;
use Waterfront\Domain\DNS\Validators\DnsValidatorFactory;

abstract class BaseRequest extends FormRequest
{
    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in($this->getModifiableRecordTypes())],
        ];
    }

    abstract protected function getValidatorFactory(): DnsValidatorFactory;

    /**
     * @{inheritDoc}
     */
    protected function getValidatorInstance(): Validator
    {
        /**
         * Override the annotation from parent class.
         *
         * @var Validator|null $validator
         */
        $validator = $this->validator;
        if ($validator !== null) {
            return $this->validator;
        }

        $factory = $this->getValidatorFactory();

        if (method_exists($this, 'validator')) {
            $validator = $this->container->call([$this, 'validator'], ['factory' => $factory]);
        } else {
            $validator = $this->createDefaultValidator($factory);
        }

        if (method_exists($this, 'withValidator')) {
            $this->withValidator($validator);
        }

        assert($validator instanceof Validator);

        $this->setValidator($validator);

        return $this->validator;
    }

    /**
     * @return array<string, string>
     */
    protected function getModifiableRecordTypes(): array
    {
        return DnsRecordTypes::getModifiable();
    }
}
