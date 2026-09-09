<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Apps\API\Waterfront\Requests\Dns\Rules\DnsCustomerRecord;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Infra\Authentication\AuthenticationManager;

class TemplateStoreRequest extends FormRequest
{
    /**
     * @throws AuthenticationException
     *
     * @return array<mixed>
     */
    public function rules(AuthenticationManager $authenticationManager, DnsRecordsValidationService $dnsRecordsValidationService): array
    {
        $customer = $authenticationManager->getAuthenticatedCustomer()->customer;

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('dns_customer_templates', 'name')
                    ->whereNull('deleted_at')
                    ->where('customer_id', $customer->id),
                ],
            'records' => ['sometimes', 'array'],
            'records.*' => ['required_with:records', new DnsCustomerRecord($dnsRecordsValidationService)],
        ];
    }
}
