<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Monarobase\CountryList\CountryListFacade as Countries;
use Propaganistas\LaravelPhone\Rules\Phone;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Vat;
use Waterfront\Apps\API\Ferry\Enum\ImplementableProducts;
use Waterfront\Apps\API\Ferry\Rules\CustomerEmailAllowedInMigration;
use Waterfront\Apps\API\Ferry\Rules\MigrationCustomerCanCreateDiscount;
use Waterfront\Apps\API\Ferry\Rules\MigrationCustomerHasProductAndPrice;
use Waterfront\Apps\API\Ferry\Rules\NonMigratedSubscriptionAlreadyExists;
use Waterfront\Apps\API\Ferry\Rules\RedirectSourceDomainIsPartOfSubscriptionRule;
use Waterfront\Apps\API\Waterfront\Requests\Customer\Rules\VatCode;
use Waterfront\Apps\API\Waterfront\Requests\CustomerWallet\Rules\IBAN;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\Domains\Rules\DomainHasNoSubdomainRule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\MigrationsPriceDiscounts;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Infra\Common\PublicSuffixList;
use Waterfront\Infra\Translation\Translator;

class MigrationValidationLibrary
{
    /**
     * @param array<string, mixed> $customerData
     *
     * @return array<string, mixed>
     */
    public static function customerRules(
        MigrationsPriceDiscounts $migrationsPriceDiscounts,
        PriceResolver $priceResolver,
        Translator $translator,
        LoggerInterface $logger,
        Repository $cache,
        Vat $vat,
        array $customerData
    ): array {
        /** @var array<mixed> $countries */
        $countries = Countries::getList('nl');

        /** @var string $customerCountryCode */
        $customerCountryCode = Arr::get($customerData, 'addresses.0.countryCode', '');

        $rules = [
            'firstName'                             => ['required', 'string', 'min:1', 'max:255'],
            'lastName'                              => ['required', 'string', 'min:1', 'max:255'],
            'gender'                                => 'required|in:M,F,X',
            'email'                                 => ['required', 'email', 'max:255', new CustomerEmailAllowedInMigration()],
            'phone'                                 => ['required', (new Phone())],
            'language'                              => ['sometimes', Rule::in(Locale::cases())],
            'addresses'                             => 'required|array',
            'addresses.*.streetName'                => ['required', 'min:1', 'max:255'],
            'addresses.*.streetNumber'              => ['required', 'max:30'],
            'addresses.*.streetNumberAddition'      => ['sometimes', 'required', 'string', 'max:10'],
            'addresses.*.zipCode'                   => ['required', 'postal_code_with:countryCode'],
            'addresses.*.city'                      => ['required', 'max:255'],
            'addresses.*.countryCode'               => ['required', Rule::in(array_keys($countries))],
            'department'                            => ['nullable', 'min:1', 'max:255'],
            'organization'                          => ['nullable', 'required_with:vat_number', 'min:1', 'max:255'],
            'cocNumber'                             => ['sometimes', 'string', 'min:1', 'max:16', 'nullable'],
            'vatNumber'                             => ['sometimes', 'nullable', 'string', new VatCode($vat, $customerCountryCode, $logger, $cache)],
            'creditLimit'                           => 'int',
            'purchaseReference'                     => 'max:255',
            'paymentTerms'                          => 'required|int|min:1',
            'referenceName'                         => ['required', 'string', 'max:255', 'doesnt_end_with:dev,Dev,dry,Dry,SIT'],
            'referenceCustomerId'                   => 'required|string|max:255',
            'groupType'                             => 'required|max:255',
            'contacts'                              => 'array',
            'contacts.*.firstName'                  => ['required', 'string', 'min:1', 'max:255'],
            'contacts.*.lastName'                   => ['required', 'string', 'min:1', 'max:255'],
            'contacts.*.company'                   => ['sometimes', 'nullable', 'string', 'min:1', 'max:255'],
            'contacts.*.email'                      => 'required|email|max:255',
            'contacts.*.type'                       => ['required', Rule::in(CustomerContactType::cases())],
            'validated'                             => 'boolean',
            'internalNote'                          => 'sometimes|string|nullable',
            'customerSince'                         => 'sometimes|nullable|date_format:Y-m-d',
            'product_group_discounts'               => 'array|sometimes',
            'product_group_discounts.*.product_group_type' => [
                'required',
                'distinct',
                Rule::enum(ProductGroupType::class),
            ],
            'product_group_discounts.*.discount_percentage' => 'decimal:0,2|required|min:1|max:100',
            'products_discounts'                    => 'array|sometimes',
            'products_discounts.*'                  => [
                'required',
                new MigrationCustomerCanCreateDiscount(
                    $priceResolver,
                    $translator,
                    $migrationsPriceDiscounts
                ),
            ],
            'products_discounts.*.slug'             => 'string|required',
            'products_discounts.*.contract_period'  => 'int|required',
            'products_discounts.*.billing_period'   => 'int|required',
            'products_discounts.*.price'            => 'int|required',
            'wallet_credit_balance'                 => 'int|min:0',
            'mandates'                              => 'sometimes|nullable|array',
            'mandates.*.type'                       => 'required|in:directdebit',
            'mandates.*.signature_date'             => 'required|date_format:Y-m-d',
            'mandates.*.consumer_name'              => 'required_if:mandates.*.type,directdebit|string',
            'mandates.*.consumer_account'           => [
                'required_if:mandates.*.type,directdebit',
                'string',
                new IBAN(),
            ],
            'mandates.*.consumer_bic'               => 'present_if:mandates.*.type,directdebit|string|nullable',

            'dnsTemplates'                                 => 'sometimes|nullable|array',
            'dnsTemplates.*.name'                          => 'required|string',
            'dnsTemplates.*.reference_template_id'         => 'required|string',
            'dnsTemplates.*.records.*.reference_record_id' => 'required|string',
            'dnsTemplates.*.records.*.type'                => [
                'bail',
                'required',
                'string',
                Rule::enum(DnsRecordType::class),
            ],

            'labels'                                => 'sometimes|nullable|array',
            'labels.*'                              => 'required|string',
        ];

        if (array_key_exists('dnsTemplates', $customerData) && is_array($customerData['dnsTemplates'])) {
            $dnsTemplateRecordRules = self::getDnsTemplateRecordRules($customerData['dnsTemplates']);

            $rules = array_merge(
                $rules,
                $dnsTemplateRecordRules,
            );
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public static function subscriptionRules(
        DomainNameRule $domainNameRule,
        DomainHasNoSubdomainRule $domainHasNoSubdomainRule,
        NonMigratedSubscriptionAlreadyExists $nonMigratedSubscriptionAlreadyExistsRule,
        PriceResolver $priceResolver,
        Translator $translator,
        LoggerInterface $logger,
        Customer $customer,
        bool $pipelineRun = false
    ): array {
        $referenceCustomerModelName = MigratedCustomer::class;

        $specificRules = [];

        if (! $pipelineRun) {
            $specificRules['reference_customer_id'] = "required|exists:$referenceCustomerModelName,reference_customer_number";
        }

        $specificRules[sprintf('subscriptions.%s.*.domain', ImplementableProducts::DOMAIN_EXTENSION->value)] = [
            'bail',
            'required',
            'distinct',
            $domainNameRule,
            $domainHasNoSubdomainRule,
            $nonMigratedSubscriptionAlreadyExistsRule,
        ];
        $specificRules[sprintf('subscriptions.%s.*.extension', ImplementableProducts::DOMAIN_EXTENSION->value)] = 'required|string';

        $specificRules[sprintf('subscriptions.%s.*.domain', ImplementableProducts::SSL->value)] = [
            'required',
            'distinct',
            $domainNameRule,
            $nonMigratedSubscriptionAlreadyExistsRule,
        ];

        $specificRules[sprintf('subscriptions.%s', ImplementableProducts::VOLUME_DISCOUNT->value)] = [
            'sometimes',
            'array',
            'between:0,1',
        ];

        return [
            ...self::getRequiredRulesForAllProducts(
                $customer,
                $domainNameRule,
                $domainHasNoSubdomainRule,
                $nonMigratedSubscriptionAlreadyExistsRule,
                $priceResolver,
                $logger,
                $pipelineRun
            ), ...$specificRules,
        ];
    }

    /**
     * @return string[]
     */
    public static function customerMessages(): array
    {
        return [
            'paymentTerms.min' => 'PaymentTerms should be a positive integer and bigger than 0.',
            'wallet_credit_balance.min' => 'Wallet Credit balance should be zero or a positive number',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getRedirectBaseRules(
        DomainNameRule $domainNameRule,
        PublicSuffixList $publicSuffixList,
        Customer|null $customer,
        string|null $domain
    ): array {
        $sourceRules = [
            'sometimes',
            $domainNameRule,
            function (string $key, string $value, Closure $fail) use ($domain) {
                if ($domain !== null && ! Str::contains($value, $domain)) {
                    $fail(sprintf(
                        'The given subscription domain %s is not compatible with the given source %s',
                        $domain,
                        $value,
                    ));
                }
            },
        ];

        if ($customer !== null) {
            $sourceRules = array_merge($sourceRules, [new RedirectSourceDomainIsPartOfSubscriptionRule($customer, $publicSuffixList)]);
        }

        return [
            '*' => ['sometimes', 'array'],
            '*.source' => $sourceRules,
            '*.destination' => [
                'sometimes',
                'url',
            ],
            '*.type'        => [
                'sometimes',
                'string',
                new Enum(RedirectType::class),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDomainBaseRules(): array
    {
        return [
            '*.reference_subscription_id' => [
                'sometimes',
                'string',
            ],
            '*.reference_domain_provider_business_unit_slug' => [
                'sometimes',
                'string',
                'exists:domain_provider_business_unit,slug',
            ],
            '*.domain_data' => [
                'sometimes',
                'array',
            ],
            '*.domain_data.reference_dns_template_id' => [
                'required_with:*.domain_data',
                'nullable',
            ],
            '*.driver' => [
                'sometimes',
                'nullable',
                Rule::in([
                    ProviderSlug::OPEN_PROVIDER->value,
                    ProviderSlug::REALTIME_REGISTER->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getBackupBaseRules(): array
    {
        return [
            '*.reference_subscription_id' => [
                'required',
                'string',
            ],
            '*.backup_data' => [
                'required',
                'array',
            ],
            '*.backup_data.bu_tenant_uuid' => [
                'required',
                'uuid',
                'exists:acronis_providers,tenant_uuid',
            ],
            '*.backup_data.customer_tenant_uuid' => [
                'required',
                'uuid',
            ],
            '*.backup_data.user_uuid' => [
                'required',
                'uuid',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getHostingBaseRules(): array
    {
        return [
            '*' => 'required|array',
            '*.hostname' => 'required|string|exists:hosting_servers,hostname',
            '*.reference_subscription_id' => 'required|string',
            '*.driver' => [
                'required',
                Rule::in([
                    ProviderSlug::DIRECTADMIN->value,
                    ProviderSlug::PLESK->value,
                ]),
            ],
            '*.server_data' => 'required|array',
            '*.server_data.directadmin_customer_name' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::DIRECTADMIN->value
                ),
                'string',
            ],
            '*.server_data.plesk_customer_username' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::PLESK->value
                ),
                'string',
            ],
            '*.server_data.plesk_customer_id' => [
                'sometimes',
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getResellerHostingBaseRules(): array
    {
        return [
            '*' => 'required|array',
            '*.hostname' => 'required|string|exists:hosting_servers,hostname',
            '*.reference_subscription_id' => 'required|string',
            '*.driver' => [
                'required',
                Rule::in([
                    ProviderSlug::DIRECTADMIN->value,
                ]),
            ],
            '*.server_data' => 'required|array',
            '*.server_data.directadmin_customer_name' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::DIRECTADMIN->value
                ),
                'string',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getMailOnlyBaseRules(): array
    {
        return [
            '*' => 'required|array',
            '*.hostname' => 'required|string|exists:hosting_servers,hostname',
            '*.reference_subscription_id' => 'required|string',
            '*.driver' => [
                'required',
                Rule::in([
                    ProviderSlug::DIRECTADMIN,
                    ProviderSlug::PLESK,
                ]),
            ],
            '*.server_data' => 'required|array',
            '*.server_data.directadmin_customer_name' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::DIRECTADMIN->value
                ),
                'string',
            ],
            '*.server_data.plesk_customer_username' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::PLESK->value
                ),
                'string',
            ],
            '*.server_data.plesk_customer_id' => [
                'sometimes',
                'nullable',
                'integer',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSitebuilderBaseRules(): array
    {
        return [
            '*' => 'required|array',
            '*.reference_subscription_id' => 'required|string',

            // Mail-only
            '*.bundle.mail_only.hostname' => 'required|string|exists:hosting_servers,hostname',
            '*.bundle.mail_only.driver' => [
                'required',
                Rule::in([
                    ProviderSlug::DIRECTADMIN,
                    ProviderSlug::PLESK,
                ]),
            ],
            '*.bundle.mail_only.server_data' => 'required|array',
            '*.bundle.mail_only.server_data.directadmin_customer_name' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::DIRECTADMIN->value
                ),
                'string',
            ],
            '*.bundle.mail_only.server_data.plesk_customer_username' => [
                sprintf(
                    'required_if:*.driver,%s',
                    ProviderSlug::PLESK->value
                ),
                'string',
            ],
            '*.bundle.mail_only.server_data.plesk_customer_id' => [
                'sometimes',
                'nullable',
                'integer',
            ],

            // Sitebuilder
            '*.bundle.sitebuilder.hostname' => 'required|string|exists:hosting_servers,hostname',
            '*.bundle.sitebuilder.driver' => [
                'required',
                Rule::in([
                    ProviderSlug::BASEKIT,
                ]),
            ],
            '*.bundle.sitebuilder.server_data.basekit_user_ref' => [
                sprintf(
                    'required_if:*.bundle.sitebuilder.driver,%s',
                    ProviderSlug::BASEKIT->value
                ),
                'integer',
            ],
            '*.bundle.sitebuilder.server_data.basekit_site_ref' => [
                sprintf(
                    'required_if:*.bundle.sitebuilder.driver,%s',
                    ProviderSlug::BASEKIT->value
                ),
                'integer',
            ],
        ];
    }

    /**
     * @param array<int, array<string, array<string, array<string, mixed>>>> $dnsTemplates
     *
     * @return array<string, mixed>
     */
    private static function getDnsTemplateRecordRules(array $dnsTemplates): array
    {
        /** @var DnsRecordsValidationService $dnsRecordsValidationService */
        $dnsRecordsValidationService = App::make(DnsRecordsValidationService::class);

        $dnsTemplateRecordRules = [];

        foreach ($dnsTemplates as $templateIndex => $dnsTemplate) {
            /** @var array<int, array<string, mixed>> $records */
            $records = Arr::array($dnsTemplate, 'records', []);
            foreach ($records as $recordIndex => $record) {
                $recordType = is_string($record['type']) ? $record['type'] : '';

                /** @var array<string, array<mixed>> $recordRules */
                $recordRules = $dnsRecordsValidationService->getRecordRules($recordType, []);

                // We don't want to override the enum rule
                unset($recordRules['type']);

                // Extra record name rules
                $recordRules['name'][] =
                    function (string $attribute, mixed $value, Closure $fail) {
                        if (! is_string($value)) {
                            return;
                        }

                        // Specific for Versio 1.0 migrations
                        if (! str_contains($value, '@') || str_contains($value, '|DOMAIN|')) {
                            $fail('Record name must contain an @ and not contain |DOMAIN|');
                        }
                    };

                // Extra record content rules
                $recordRules['content'][] =
                    function (string $attribute, mixed $value, Closure $fail) {
                        if (! is_string($value)) {
                            return;
                        }

                        // Specific for Versio 1.0 migrations
                        if (str_contains($value, '|DOMAIN|')) {
                            $fail('Record content must contain an @ and not contain |DOMAIN|');
                        }
                    };

                foreach ($recordRules as $recordAttribute => $rulesPerAttribute) {
                    $dnsTemplateRecordRules["dnsTemplates.$templateIndex.records.$recordIndex.$recordAttribute"] = $rulesPerAttribute;
                }
            }
        }

        return $dnsTemplateRecordRules;
    }

    /**
     * @return array<string, mixed>
     */
    private static function getRequiredRulesForAllProducts(
        Customer $customer,
        DomainNameRule $domainNameRule,
        DomainHasNoSubdomainRule $domainHasNoSubdomainRule,
        NonMigratedSubscriptionAlreadyExists $nonMigratedSubscriptionAlreadyExistsRule,
        PriceResolver $priceResolver,
        LoggerInterface $logger,
        bool $pipelineRun = false,
    ): array {
        $rules    = [];
        foreach (ImplementableProducts::cases() as $implementableProduct) {
            $groupSlug = ImplementableProducts::getProductGroupTypeSlug($implementableProduct);
            $productKey = $implementableProduct->value;

            $rules[sprintf('subscriptions.%s', $productKey)] = 'sometimes|array';
            $rules[sprintf('subscriptions.%s.*', $productKey)] = new MigrationCustomerHasProductAndPrice(
                priceResolver: $priceResolver,
                logger: $logger,
                customer: $customer,
                productGroupType: $groupSlug,
            );

            $rules[sprintf('subscriptions.%s.*.slug', $productKey)] = [
                'bail',
                'required',
                'string',
                function (string $key, string $value, Closure $fail) use ($groupSlug) {
                    $productExists = Product::query()
                        ->whereHas(
                            'productGroup',
                            fn (Builder $query) => $query->where('slug', $groupSlug)
                        )
                        ->where('slug', $value)
                        ->exists();

                    if (! $productExists) {
                        $fail(sprintf(
                            'Product %s not found for product group %s',
                            $value,
                            $groupSlug->value,
                        ));
                    }
                },
            ];
            $rules[sprintf('subscriptions.%s.*.domain', $productKey)] = [
                'sometimes',
                'required',
                'distinct',
                $domainNameRule,
                $domainHasNoSubdomainRule,
                $nonMigratedSubscriptionAlreadyExistsRule,
            ];
            $oldestStartDateAllowed = CarbonImmutable::createFromDate(1980)->startOfYear();
            $oldestNextDateAllowed = CarbonImmutable::now()->subYear()->startOfYear();
            $oldestCancelDateAllowed = CarbonImmutable::now()->subYears(5)->startOfYear();

            $rules[sprintf('subscriptions.%s.*.contract_period', $productKey)] = 'required|min:1|integer:strict';
            $rules[sprintf('subscriptions.%s.*.billing_period', $productKey)] = 'required|min:1|integer:strict';
            $rules[sprintf('subscriptions.%s.*.start_date', $productKey)] = ['required', 'date',  Rule::date()->after($oldestStartDateAllowed)];
            $rules[sprintf('subscriptions.%s.*.next_contract_date', $productKey)] = ['required', 'date',  Rule::date()->after($oldestNextDateAllowed)];
            $rules[sprintf('subscriptions.%s.*.next_billing_date', $productKey)] = ['required', 'date',  Rule::date()->after($oldestNextDateAllowed)];
            $rules[sprintf('subscriptions.%s.*.cancel_date', $productKey)] = ['sometimes', 'nullable', 'date',  Rule::date()->after($oldestCancelDateAllowed)];
            $rules[sprintf('subscriptions.%s.*.reference_subscription_id', $productKey)] = 'required|string';
            $rules[sprintf('subscriptions.%s.*.reference_product_id', $productKey)] = 'required|string';
            $rules[sprintf('subscriptions.%s.*.reference_net_price', $productKey)] = [
                sprintf('required_if:subscriptions.%s.*.reference_net_price_is_fixed,true', $productKey),
                'integer:strict',
                'min:0',
            ];
            $rules[sprintf('subscriptions.%s.*.reference_net_price_is_fixed', $productKey)] = 'sometimes|bool';
            $rules[sprintf('subscriptions.%s.*.reference_net_price_is_one_off', $productKey)] = [
                sprintf('required_if:subscriptions.%s.*.reference_net_price_is_fixed,true', $productKey),
                'bool:strict',
            ];
            $rules[sprintf('subscriptions.%s.*.internal_comment', $productKey)] = 'sometimes|string';
            $rules[sprintf('subscriptions.%s.*.labels', $productKey)] = 'sometimes|nullable|array';

            if (! $pipelineRun) {
                $rules[sprintf('subscriptions.%s.*.labels.*', $productKey)] = 'required|string';
            } else {
                $rules[sprintf('subscriptions.%s.*.labels.*', $productKey)] = 'required|string|in_array:customer.labels.*';
            }
        }

        return $rules;
    }
}
