<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Domains\Rules\DomainHasNoSubdomainRule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Ferry\Enums\ManualMigrationDomainProvider;
use Waterfront\Domain\Ferry\Enums\ManualMigrationOption;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property string                       $domain_name
 * @property non-empty-string             $reference_subscription_id
 * @property non-empty-string             $reference_customer_number
 * @property non-empty-string             $source_business_unit
 * @property ?string                      $source_domain_provider
 * @property int                          $contract_period
 * @property int                          $billing_period
 * @property ?string                      $internal_comment
 * @property ?list<ManualMigrationOption> $options
 * @property string                       $product_uuid
 */
class ManualMigrationValidateRequest extends FormRequest
{
    /**
     * @return mixed[]
     */
    public function rules(): array
    {
        $domainNameRule = Container::getInstance()->make(DomainNameRule::class);
        $domainHasNoSubdomainRule = Container::getInstance()->make(DomainHasNoSubdomainRule::class);
        $subscriptionRepo = Container::getInstance()->make(SubscriptionRepository::class);
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        return [
            'domain_name' => [
                'required',
                'string',
                $domainHasNoSubdomainRule,
                $domainNameRule,
                function (string $attribute, mixed $value, Closure $fail) use ($subscriptionRepo, $translator) {
                    assert(is_string($value));
                    $domainAlreadyInUse = $subscriptionRepo->domainAlreadyInUse($value, ProductGroupType::EXTENSION->value);

                    if ($domainAlreadyInUse) {
                        $fail($translator->translate('validation.product_group_already_exists_on_domain'));
                    }
                },
            ],
            'product_uuid' => ['required', 'exists:products,uuid'],
            'billing_period' => ['required', 'integer'],
            'contract_period' => ['required', 'integer'],
            'reference_product_id' => ['required', 'string'],
            'reference_subscription_id' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'next_billing_date' => ['required', 'date', 'after:start_date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'internal_comment' => ['string'],
            'source_business_unit' => ['required', 'string'],
            'reference_customer_number' => ['required', 'string'],
            'source_domain_provider' => ['sometimes', 'required', Rule::enum(ManualMigrationDomainProvider::class)],
        ];
    }
}
