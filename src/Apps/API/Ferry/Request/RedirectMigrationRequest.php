<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest as BaseRequest;
use Waterfront\Apps\API\Ferry\Request\Rules\RedirectMigrationRules;
use Waterfront\Domain\Customers\Models\Customer;

class RedirectMigrationRequest extends BaseRequest
{
    public function __construct(
        private readonly RedirectMigrationRules $redirectMigrationRules,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return $this->redirectMigrationRules->getRules(
            customer: $customer,
            domain: null,
        );
    }
}
