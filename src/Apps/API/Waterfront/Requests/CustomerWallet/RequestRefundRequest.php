<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CustomerWallet;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

/**
 * @property Customer $customer
 */
class RequestRefundRequest extends FormRequest
{
    /**
     * @return array<string,array<int,string|FilterSpecialChars|IBAN>>
     */
    public function rules(): array
    {
        /**
         * @see https://en.wikipedia.org/wiki/International_Bank_Account_Number
         * at time of implementation, the lowest number of character was 15
         */
        return [
            'bank_account_name' => ['required', 'max:255', 'min:1', new FilterSpecialChars('.')],
            'bank_account_number' => ['required', 'max:255', 'min:15', new FilterSpecialChars(), new IBAN()],
        ];
    }
}
