<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

class UpdateSubscriptionRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        $domainNameRule = Container::getInstance()->make(DomainNameRule::class);

        return [
            'domain' => [
                'required',
                'string',
                $domainNameRule,
            ],
            'administrative_status' => ['required', Rule::in(AdministrativeStatus::cases(), 'value')],
            'technical_status' => ['required', Rule::in(TechnicalStatus::cases(), 'value')],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'net_price' => ['required', 'integer', 'min:0'],
            'gross_price' => ['required', 'integer', 'min:0'],
        ];
    }
}
