<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\DomainName;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int    $contactId
 * @property string $transferCode
 */
class RetryPovisioningRequest extends FormRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'contactId' => ['integer', 'required'],
            'transferCode' => ['string'],
        ];
    }
}
