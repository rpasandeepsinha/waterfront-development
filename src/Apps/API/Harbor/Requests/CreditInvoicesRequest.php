<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Harbor\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreditInvoicesRequest extends FormRequest
{
    /**
     * @return string[]
     */
    public function rules(): array
    {
        return [
            'invoicesToCredit' => 'array|required',
            'invoicesToCredit.*.waterfrontInvoiceId' => 'required|integer|numeric|exists:invoices,id',
            'invoicesToCredit.*.amountToCredit' => 'required|integer|numeric',
            'invoicesToCredit.*.shouldCreateNewInvoice' => 'required|boolean',
            'invoicesToCredit.*.creditReason' => 'sometimes|nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoicesToCredit.*.waterfrontInvoiceId.exists' => 'Invoice id :input does not exist in Waterfront.',
        ];
    }
}
