<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use JsonException;
use Waterfront\Domain\Cart\Validators\Rules\AddonIsCorrectlyAttached;
use Waterfront\Domain\Cart\Validators\Rules\DnsRules;
use Waterfront\Domain\Cart\Validators\Rules\DoesChildProductItemHaveRelationWithParentProductRule;
use Waterfront\Domain\Cart\Validators\Rules\DomainRules;
use Waterfront\Domain\Cart\Validators\Rules\ProductChangeIsAllowed;
use Waterfront\Domain\Cart\Validators\Rules\SslRules;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Rules\DomainHasNoSubdomainRule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Hosting\Rules\OrderHostingRules;
use Waterfront\Domain\Microsoft365\Rules\Microsoft365Rules;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductPriceType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Redirects\Rules\OrderRedirectRules;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Domain\Subscriptions\Rules\BillingPeriodRules;
use Waterfront\Domain\Subscriptions\Rules\ManualSubscriptionRules;
use Waterfront\Domain\VPS\Rules\CloudStackVirtualMachineRules;
use Waterfront\Infra\Translation\TranslatorInterface;

class CartValidator extends Validator
{
    /**
     * @param mixed[] $data
     * @param mixed[] $rules
     * @param mixed[] $messages
     * @param mixed[] $customAttributes
     *
     * @throws JsonException
     */
    public function __construct(
        private readonly Customer $customer,
        private readonly ProductRepository $productRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly BillingPeriodRules $billingPeriodRules,
        private readonly DomainRules $domainRules,
        private readonly SslRules $sslRules,
        private readonly DnsRules $dnsRules,
        private readonly OrderHostingRules $orderHostingRules,
        private readonly OrderRedirectRules $orderRedirectRules,
        private readonly CloudStackVirtualMachineRules $cloudStackVirtualMachineRules,
        private readonly ManualSubscriptionRules $manualSubscriptionRules,
        private readonly Microsoft365Rules $microsoft365Rules,
        private readonly TranslatorInterface $wfTranslator,
        private readonly DomainNameRule $domainNameRule,
        private readonly DomainHasNoSubdomainRule $subDomainNameRule,
        private readonly DoesChildProductItemHaveRelationWithParentProductRule $childProductItemHaveRelationWithParentProductRule,
        private readonly AddonIsCorrectlyAttached $addonIsCorrectlyAttached,
        private readonly ProductChangeIsAllowed $productChangeIsAllowed,
        Translator $translator,
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = [],
    ) {
        parent::__construct(
            $translator,
            $data,
            $rules + $this->getValidationRules($data),
            $messages,
            $customAttributes,
        );
    }

    /**
     * @param array<mixed> $data
     *
     * @throws JsonException
     *
     * @return array<mixed>
     */
    private function getValidationRules(array $data): array
    {
        // Used to verify `exists` rule.
        $this->setPresenceVerifier(Container::getInstance()->make('validation.presence'));

        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        Log::info("CreateOrderValidator:: getting validation for the following payload: $encoded");

        return array_merge(
            [
                // General order validation
                'payment_method' => ['required', 'string'],
                'create_direct_debit_mandate' => ['nullable', 'boolean'],
                'vouchers' => ['nullable', 'array'],
                'vouchers.*' => ['nullable', 'required', 'string'],
                // General product validation
                'subscriptions.*.*.uuid' => ['required', 'uuid'],
                'subscriptions.*.*.status' => [
                    'required',
                    Rule::in([ProductPriceType::REGISTRATION, ProductPriceType::PROLONGATION]),
                ],
                'subscriptions.*.*.contract_period' => ['required', 'integer', 'min:1'],
                'subscriptions.*.*.billing_period' => ['required', 'integer', 'min:1', $this->billingPeriodRules],
                'subscriptions.*.*.parent_subscription_uuid' => [
                    'sometimes',
                    'missing_with:subscriptions.*.*.subscription_uuid',
                    Rule::exists(Subscription::class, 'uuid')->where(
                        'administrative_status',
                        AdministrativeStatus::ACTIVE->value,
                    ),
                ],
                'subscriptions.*.*.subscription_uuid' => [
                    'sometimes',
                    'missing_with:subscriptions.*.*.parent_subscription_uuid',
                    Rule::exists(Subscription::class, 'uuid')->where(
                        'administrative_status',
                        AdministrativeStatus::ACTIVE->value,
                    ),
                ],
                'subscriptions.*.*.domain' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'exclude_if:subscriptions.*.*.status,prolongation',
                    $this->domainNameRule,
                    $this->subDomainNameRule,
                    function (string $attribute, mixed $value, Closure $fail) {
                        // Example $attribute value: subscriptions.ssl.0.slug
                        $productGroup = explode('.', $attribute)[1];
                        /** @var string $value */
                        $domainAlreadyInUse = $this->subscriptionRepository->domainAlreadyInUse($value, $productGroup);

                        if ($domainAlreadyInUse) {
                            $fail($this->wfTranslator->translate('validation.product_group_already_exists_on_domain'));
                        }
                    },
                ],
                'subscriptions.*.*.slug' => [
                    'bail',
                    'required',
                    'string',
                    function (string $attribute, mixed $value, Closure $fail) {
                        // Example $attribute value: subscriptions.ssl.0.slug
                        $productGroup = explode('.', $attribute)[1];
                        /** @var string $value */
                        $valid = $this->productRepository->slugExistsForGroup(
                            $value,
                            ProductGroupType::from($productGroup),
                        );

                        if (! $valid) {
                            $fail($this->translator->get('validation.in'));
                        }
                    },
                ],
                'subscriptions.*.*' => ['required', 'array'],

                // Child product validation
                'subscriptions.*.*.children.*.*' => ['sometimes', 'array'],
                'subscriptions.*.*.children.*.*.domain' => ['sometimes', 'required', 'string', $this->domainNameRule],
                'subscriptions.*.*.children.*.*.slug' => [
                    'bail',
                    'required',
                    'string',
                    function (string $attribute, mixed $value, Closure $fail) {
                        // Example $attribute value: subscriptions.domain.0.children.dns.0.slug
                        $productGroup = explode('.', $attribute)[4];
                        /** @var string $value */
                        $valid = $this->productRepository->slugExistsForGroup(
                            $value,
                            ProductGroupType::from($productGroup),
                        );

                        if (! $valid) {
                            $fail($this->translator->get('validation.in'));
                        }
                    },
                ],
                'subscriptions.*' => [
                    $this->childProductItemHaveRelationWithParentProductRule,
                    $this->addonIsCorrectlyAttached,
                    $this->productChangeIsAllowed,
                ],
            ],
            $this->domainRules->getDomainRules(),
            $this->sslRules->getSslRules(),
            $this->dnsRules->getDnsRules(),
            $this->orderHostingRules->getHostingRules($data),
            $this->orderRedirectRules->getRedirectRules(),
            $this->cloudStackVirtualMachineRules->getCloudStackVirtualMachineRules($this->customer),
            $this->manualSubscriptionRules->getManualSubscriptionRules(),
            $this->microsoft365Rules->getMicrosoft365Rules($this->customer),
        );
    }
}
