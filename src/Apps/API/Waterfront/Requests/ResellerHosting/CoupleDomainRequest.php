<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\ResellerHosting;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Domains\Rules\DomainNameRule;

class CoupleDomainRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        $domainNameRule = $this->container->make(DomainNameRule::class);

        return [
            'uuid' => ['required', 'uuid', 'exists:subscriptions,uuid'],
            'reseller_sub_username' => ['required', 'string', 'max:20', 'alpha_num'],
            'domain' => [
                'required',
                'string',
                'between:3,255',
                $domainNameRule,
                'exists:subscriptions,domain',
            ],
        ];
    }

    protected function passesAuthorization(): bool
    {
        return ! $this->getValidatorInstance()->fails() && parent::passesAuthorization();
    }
}
