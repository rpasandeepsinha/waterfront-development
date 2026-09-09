<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use Waterfront\Apps\API\Ferry\Request\Rules\CustomerMigrationRules;

class CustomerMigrationCreateRequest extends BaseRequest
{
    public function __construct(
        private readonly CustomerMigrationRules $customerMigrationRules,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return $this->customerMigrationRules->getRules(
            customerData: $this->request->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return MigrationValidationLibrary::customerMessages() + parent::messages();
    }
}
