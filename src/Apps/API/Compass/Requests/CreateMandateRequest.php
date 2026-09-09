<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;
use Waterfront\Support\Helpers\ValidationRules\ReservedBankAccounts;

/**
 * @property string $consumer_name
 * @property string $consumer_account
 * @property string $signature_date
 */
class CreateMandateRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'consumer_account' => ['required', 'string', 'max:64', new IBAN(), new ReservedBankAccounts()],
            'consumer_name' => ['required', 'string', 'max:64', new FilterSpecialChars('.&')],
            'signature_date' => ['required', 'string'],
        ];
    }
}
