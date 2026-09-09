<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;
use Waterfront\Apps\API\Waterfront\Requests\Dns\Rules\DnsCustomerRecord;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;

/**
 * @mixin Route
 *
 * @property DnsCustomerTemplate $template
 */
class TemplateUpdateRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(DnsRecordsValidationService $dnsRecordsValidationService): array
    {
        return [
            //Ignores own name and gives 422 on existing names
            'name' => [
                'required',
                'string',
                Rule::unique('dns_customer_templates', 'name')
                    ->whereNull('deleted_at')
                    ->where('customer_id', $this->template->customer_id)
                    ->ignore($this->template->id, 'id'),
            ],
            'records' => ['sometimes', 'array'],
            'records.*' => ['required_with:records', new DnsCustomerRecord($dnsRecordsValidationService)],
        ];
    }
}
