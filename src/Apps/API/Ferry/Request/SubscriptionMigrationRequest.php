<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Apps\API\Ferry\Request\Rules\SubscriptionMigrationRules;
use Waterfront\Domain\Customers\Models\Customer;

class SubscriptionMigrationRequest extends FormRequest
{
    public function __construct(
        private readonly SubscriptionMigrationRules $subscriptionMigrationRules,
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

        return $this->subscriptionMigrationRules->getRules($customer);
    }
}
