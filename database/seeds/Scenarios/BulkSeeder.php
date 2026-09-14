<?php

declare(strict_types=1);

namespace Database\Seeders\Scenarios;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\Console\Output\ConsoleOutput;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Webmozart\Assert\Assert;

/**
 * If you want to debug in your local dev with a lot of customers/subscriptions.
 *
 * Meant to be fast, not pretty.
 *
 * (optional) Change the config in:
 *      config/bulk-seeder.php
 *
 * Then run with:
 *      php artisan db:seed --class=Database\\Seeders\\Scenarios\\BulkSeeder
 */
class BulkSeeder extends Seeder
{
    private readonly int $customerIdStart;

    private Product $nlProduct;

    private Product $basicHostingProduct;

    private Product $dnsProduct;

    private string $createdUpdatedDatetime;

    private string $nowDate;

    private string $nowDatePlusOneYear;

    private int $domainPlaceholderProviderId;

    private int $hostingPlaceholderProviderId;

    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly ConsoleOutput $output,
    ) {
        ini_set('memory_limit', -1);

        $maxCustomerId = Customer::max('id');
        Assert::nullOrNumeric($maxCustomerId);

        $this->customerIdStart = intval($maxCustomerId) + 1;
    }

    private function setUp(): void
    {
        $this->nlProduct = Product::where('slug', 'extension_nl')->firstOrFail();

        $this->basicHostingProduct = Product::where('slug', 'hosting_brons')->firstOrFail();

        $this->dnsProduct = Product::where('slug', 'free-dns')->firstOrFail();

        $domainPlaceholderProvider = Provider::where('slug', ProviderSlug::PLACEHOLDER)
            ->where('type', ProviderType::DOMAIN)
            ->firstOrFail();

        $hostingPlaceholderProvider = Provider::where('slug', ProviderSlug::PLACEHOLDER)
            ->where('type', ProviderType::HOSTING)
            ->firstOrFail();

        $this->domainPlaceholderProviderId = $domainPlaceholderProvider->id;
        $this->hostingPlaceholderProviderId = $hostingPlaceholderProvider->id;

        $this->createdUpdatedDatetime = CarbonImmutable::now()->toDateTimeString();
        $this->nowDate = CarbonImmutable::now()->toDateString();
        $this->nowDatePlusOneYear = CarbonImmutable::now()->addYear()->toDateString();
    }

    public function run(): void
    {
        $this->setUp();

        $this->seedCustomers();

        $this->seedDiscount();

        $definitions = $this->makeDefinitions();

        $this->seedSubscriptions($definitions);

        $this->seedChildSubscriptions();

        $this->updateSubscriptionsToRenewable();

        $this->updateSubscriptionsToInvoiceable();

        $this->updateSubscriptionsToCancelled();

        $this->insertFerryMigrationData();
    }

    private function seedCustomers(): void
    {
        $customerAmount = $this->configuration->getAsInteger('bulk-seeder.customer_amount');

        $this->output->writeln("generating $customerAmount customers...");

        $customersToCreate = [];

        // When you restart the bulk seeder, the "unique" customer number we get from the faker
        // could already exist in the database, that's why we count it here ourselves.
        $maxCustomerNumber = Customer::max('customer_number');
        Assert::nullOrNumeric($maxCustomerNumber);

        $customerNumber = intval($maxCustomerNumber) + 1;

        for ($i = 1; $i <= $customerAmount; $i++) {
            $customersToCreate[] = [
                'uuid' => Uuid::uuid4(),
                'customer_number' => $customerNumber,
                'organization' => null,
                'department' => null,
                'first_name' => 'Firstname-' . $customerNumber,
                'last_name' => 'Lastname-' . $customerNumber,
                'gender' => '',
                'invoice_history_url' => null,
                'admin_url' => null,
                'phone_country_code' => 31,
                'phone_area_code' => 6,
                'phone_subscriber_number' => 87281426,
                'email' => $customerNumber . '@sandwave.io',
                'locale' => Locale::DUTCH->value,
                'terms_of_payment' => 14,
                'payment_type' => PaymentType::CREDIT,
                'terms_accepted' => true,
                'created_at' => $this->createdUpdatedDatetime,
                'updated_at' => $this->createdUpdatedDatetime,
                'anonymized_at' => null,
                'customer_since' => Arr::random(['2015-03-16', '2020-05-04', '2025-06-14']),
            ];

            $customerNumber++;
        }

        $this->output->writeln("now inserting $customerAmount customers...");

        foreach (array_chunk($customersToCreate, 1000) as $customersInsertData) {
            Customer::insert($customersInsertData);

            /** @var array<int> $customerIds */
            $customerIds = DB::table('customers')->orderBy('id', 'desc')->limit(1000)->pluck('id')->toArray();

            // Since we fetch the latest customer ids descending, we want to insert the lowest customer id first
            natsort($customerIds);

            $addresses = [];

            foreach ($customerIds as $customerId) {
                $addresses[] = [
                    'customer_id' => $customerId,
                    'street_name' => 'Teststreet',
                    'street_number' => random_int(1, 99),
                    'zip_code' => random_int(1000, 9999) . ' AB',
                    'city' => 'Testcity',
                    'country_code' => 'NL',
                    'created_at' => $this->createdUpdatedDatetime,
                    'updated_at' => $this->createdUpdatedDatetime,
                ];
            }

            CustomerAddress::insert($addresses);
        }

        $this->output->writeln('customers inserted');
    }

    private function seedDiscount(): void
    {
        $coupledAmount = $this->configuration->getAsInteger('bulk-seeder.customers_coupled_to_discount');

        if ($coupledAmount < 1) {
            return;
        }

        $productDiscount = new ProductDiscount();
        $productDiscount->name = 'Bulk seeder discount';
        $productDiscount->save();

        $price = new ProductPriceComponent();
        $price->type = PriceComponentType::PROLONGATION;
        $price->product_id = $this->nlProduct->id;
        $price->contract_period = 12;
        $price->billing_period = 12;
        $price->price = 50;
        $price->orderable = true;
        $price->starts_at = CarbonImmutable::now();
        $price->save();

        DB::table('product_discount_prices')->insert([
            'product_discount_id' => $productDiscount->id,
            'price_id' => $price->id,
        ]);

        $customers = Customer::query()
            ->whereDoesntHave('productDiscounts')
            ->inRandomOrder()
            ->limit($coupledAmount)
            ->get();

        $productDiscount->customers()->attach($customers);
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function makeDefinitions(): array
    {
        $subscriptionsForCustomerTotal = $this->configuration->getAsInteger('bulk-seeder.customer_amount');
        $subscriptionsForCustomer100 = $this->configuration->getAsInteger(
            'bulk-seeder.customers_for_100_subscriptions',
        );
        $subscriptionsForCustomer1000 = $this->configuration->getAsInteger(
            'bulk-seeder.customers_for_1000_subscriptions',
        );
        $subscriptionsForCustomer10000 = $this->configuration->getAsInteger(
            'bulk-seeder.customers_for_10000_subscriptions',
        );

        $makeDefinitions = [
            [
                'customerAmount' => $subscriptionsForCustomer100,
                'domainAmount' => 90,
                'hostingAmount' => 10,
            ],
            [
                'customerAmount' => $subscriptionsForCustomer1000,
                'domainAmount' => 900,
                'hostingAmount' => 100,
            ],
            [
                'customerAmount' => $subscriptionsForCustomer10000,
                'domainAmount' => 9000,
                'hostingAmount' => 1000,
            ],
        ];

        $fillerSubscriptionTotal =
            $subscriptionsForCustomerTotal - $subscriptionsForCustomer10000 - $subscriptionsForCustomer1000
            - $subscriptionsForCustomer100;

        if ($fillerSubscriptionTotal <= 0) {
            throw new RuntimeException("Can't have $fillerSubscriptionTotal subscriptions!");
        }

        $iterations = (int) ceil($fillerSubscriptionTotal / 10000);
        $perIteration = (int) ceil($fillerSubscriptionTotal / $iterations);

        for ($i = 1; $i <= $iterations; $i++) {
            $makeDefinitions[] = [
                'customerAmount' => $perIteration,
                'domainAmount' => 4,
                'hostingAmount' => 1,
            ];
        }

        $makeDefinitionCount = count($makeDefinitions);
        $totalSubscriptionCount = 0;
        foreach ($makeDefinitions as $makeDefinition) {
            $totalSubscriptionCount +=
                $makeDefinition['customerAmount']
                * ($makeDefinition['domainAmount'] + $makeDefinition['hostingAmount']);
        }

        $this->output->writeln(
            "generating and inserting $totalSubscriptionCount subscriptions in $makeDefinitionCount iterations",
        );

        return $makeDefinitions;
    }

    /**
     * @param array<int, array<string, int>> $makeDefinitions
     */
    private function seedSubscriptions(array $makeDefinitions): void
    {
        $currentDefinition = 1;
        $totalDefinitions = count($makeDefinitions);

        foreach ($makeDefinitions as $definition) {
            $payload = json_encode($definition);

            $this->output->writeln(
                "$currentDefinition / $totalDefinitions - starting definition making for payload: $payload",
            );

            if ($definition['customerAmount'] === 0) {
                continue;
            }

            $totalSubscriptionsToMakeDefinition = $this->expandCustomerDataAndSeedSubscriptions(
                $definition['customerAmount'],
                $definition['domainAmount'],
                $definition['hostingAmount'],
            );

            // Base subscriptions
            $baseCount = count($totalSubscriptionsToMakeDefinition['baseSubscriptions']);
            $this->output->writeln(
                "$currentDefinition / $totalDefinitions - inserting base subscriptions count: $baseCount",
            );

            foreach (array_chunk(
                $totalSubscriptionsToMakeDefinition['baseSubscriptions'],
                1000,
            ) as $baseSubscriptionInsertData) {
                DB::table('subscriptions')->insert($baseSubscriptionInsertData);
            }

            // Deployments
            $domainCount = count($totalSubscriptionsToMakeDefinition['domainDeployments']);
            $this->output->writeln(
                "$currentDefinition / $totalDefinitions - inserting domain deployments count: $domainCount",
            );

            foreach (array_chunk(
                $totalSubscriptionsToMakeDefinition['domainDeployments'],
                1000,
            ) as $domainDeploymentInsertData) {
                DB::table('domain_deployments')->insert($domainDeploymentInsertData);
            }

            $hostingCount = count($totalSubscriptionsToMakeDefinition['hostingSubscriptions']);
            $this->output->writeln(
                "$currentDefinition / $totalDefinitions - inserting hosting deployments count: $hostingCount",
            );

            foreach (array_chunk(
                $totalSubscriptionsToMakeDefinition['hostingSubscriptions'],
                1000,
            ) as $hostingSubscriptions) {
                DB::table('hosting_deployments')->insert($hostingSubscriptions);
            }

            $currentDefinition++;
        }
    }

    private function updateSubscriptionsToRenewable(): void
    {
        // amount of subs that are to be renewed
        $amountOfRenewables = $this->configuration->getAsInteger('bulk-seeder.amount_of_renewables');

        $this->output->writeln("updating $amountOfRenewables subscriptions to be renewable...");

        $subscriptions = DB::table('subscriptions')
            ->where('administrative_status', '=', AdministrativeStatus::ACTIVE->value)
            ->limit($amountOfRenewables)
            ->pluck('id')
            ->toArray();

        // chunk update per 1000 subscriptions
        foreach (array_chunk($subscriptions, 1000) as $idsChunked) {
            Subscription::query()
                ->whereIn('id', $idsChunked)
                ->update([
                    'end_date' => $this->nowDate,
                    'next_billing_date' => $this->nowDate,
                ]);
        }

        // in the past
        $amountOfYearsInThePast = $this->configuration->getAsInteger(
            'bulk-seeder.amount_of_years_in_the_past_renewables',
        );
        $amountOfYearsInThePastAmount = $this->configuration->getAsInteger(
            'bulk-seeder.amount_of_years_in_the_past_renewables_amount',
        );

        $this->output->writeln("updating $amountOfYearsInThePastAmount subscriptions to be renewable far past...");

        $subscriptions = DB::table('subscriptions')
            ->limit($amountOfYearsInThePastAmount)
            ->where('end_date', '>', $this->nowDate)
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->pluck('id')
            ->toArray();

        $renewEndDate = CarbonImmutable::now()->subYearsWithOverflow($amountOfYearsInThePast)->toDateString();

        foreach (array_chunk($subscriptions, 1000) as $idsChunked) {
            Subscription::query()
                ->whereIn('id', $idsChunked)
                ->update([
                    'end_date' => $renewEndDate,
                    'next_billing_date' => $renewEndDate,
                ]);
        }
    }

    private function updateSubscriptionsToInvoiceable(): void
    {
        $amountOfInvoicableInBetween = $this->configuration->getAsInteger(
            'bulk-seeder.amount_of_subscriptions_to_be_invoiced',
        );
        $amountOfInvoicableMonthsInThePast = $this->configuration->getAsInteger(
            'bulk-seeder.amount_of_months_in_the_past_invoicing',
        );

        $subscriptions = DB::table('subscriptions')
            ->where('end_date', '>', $this->nowDate)
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->limit($amountOfInvoicableInBetween)
            ->orderBy('id', 'desc')
            ->pluck('id')
            ->toArray();

        $this->output->writeln(
            'updating '
            . count($subscriptions)
            . " subscriptions to be invoicable $amountOfInvoicableMonthsInThePast months in the past...",
        );

        $nextBillingDate = CarbonImmutable::now()->subMonths($amountOfInvoicableMonthsInThePast)->toDateString();

        // chunk update per 1000 subscriptions
        foreach (array_chunk($subscriptions, 1000) as $idsChunked) {
            Subscription::query()
                ->whereIn('id', $idsChunked)
                ->update([
                    'next_billing_date' => $nextBillingDate,
                ]);
        }
    }

    private function updateSubscriptionsToCancelled(): void
    {
        // amount of subs that are to be cancelled
        $amountOfCancels = $this->configuration->getAsInteger('bulk-seeder.amount_of_subscriptions_to_be_canceled');

        $subscriptions = DB::table('subscriptions')
            ->where('end_date', '>', $this->nowDate)
            ->where('administrative_status', AdministrativeStatus::ACTIVE->value)
            ->limit($amountOfCancels)
            ->pluck('id')
            ->toArray();

        $this->output->writeln('updating ' . count($subscriptions) . ' subscriptions to be cancelled...');

        $cancelEndDate = CarbonImmutable::now()->subMonth()->toDateString();
        $cancelDate = CarbonImmutable::now()->subYear()->toDateString();

        // chunk update per 1000 subscriptions
        foreach (array_chunk($subscriptions, 1000) as $idsChunked) {
            Subscription::query()
                ->whereIn('id', $idsChunked)
                ->update([
                    'end_date' => $cancelEndDate,
                    'cancel_date' => $cancelDate,
                    'administrative_status' => AdministrativeStatus::CANCELED->value,
                ]);
        }
    }

    /**
     *
     * @return array<string, array<int, array<string, int|DomainStatus|string|null>>>
     */
    private function expandCustomerDataAndSeedSubscriptions(
        int $customerLimit,
        int $domainSubscriptionsPerCustomer,
        int $hostingSubscriptionsPerCustomer,
    ): array {
        $customers = DB::select(
            Customer::query()->select(['id'])->whereDoesntHave('subscriptions')->limit($customerLimit)->toSql(),
        );

        // Expand one customer in the batch with a contact + user
        $frontendReadyCustomerId = $customers[array_rand($customers)]->id;

        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $frontendReadyCustomerId;
        $contact->first_name = 'Bulk';
        $contact->last_name = 'Contact';
        $contact->email = 'test.kees.transfer.sender@sandwave.io';
        $contact->save();

        $this->output->writeln("frontend customer ID: $frontendReadyCustomerId");

        // Seed subscriptions
        $subscriptionData = [
            'baseSubscriptions' => [],
            'domainDeployments' => [],
            'hostingSubscriptions' => [],
        ];

        $nlUuid = $this->nlProduct->uuid;
        $extensionGroupSlug = $this->nlProduct->productGroup->slug;

        $basicHostingUuid = $this->basicHostingProduct->uuid;
        $hostingGroupSlug = $this->basicHostingProduct->productGroup->slug;

        foreach ($customers as $customer) {
            // Domain names
            [$baseSubscriptions, $domainDeployments] = $this->seedSubscriptionsForCustomer(
                $domainSubscriptionsPerCustomer,
                $customer->id,
                $nlUuid,
                $extensionGroupSlug,
            );

            $this->imitateMerge($subscriptionData['baseSubscriptions'], $baseSubscriptions);
            $this->imitateMerge($subscriptionData['domainDeployments'], $domainDeployments);

            // Hosting
            [$baseSubscriptions, , $hostingSubscriptions] = $this->seedSubscriptionsForCustomer(
                $hostingSubscriptionsPerCustomer,
                $customer->id,
                $basicHostingUuid,
                $hostingGroupSlug,
            );

            $this->imitateMerge($subscriptionData['baseSubscriptions'], $baseSubscriptions);
            $this->imitateMerge($subscriptionData['hostingSubscriptions'], $hostingSubscriptions);
        }

        return $subscriptionData;
    }

    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     */
    private function imitateMerge(&$array1, &$array2): void
    {
        foreach ($array2 as $i) {
            $array1[] = $i;
        }
    }

    /**
     * @return array<int, array<int, array<string, string|DomainStatus|int|null>>>
     */
    private function seedSubscriptionsForCustomer(
        int $amount,
        int $customerId,
        string $productUuid,
        ProductGroupType $productGroupSlug,
    ): array {
        $baseSubscriptions = [];
        $domainsSubscriptions = [];
        $hostingSubscriptions = [];

        for ($i = 1; $i <= $amount; $i++) {
            $startDate = $this->nowDate;
            $endDate = $this->nowDatePlusOneYear;
            $nextBillingDate = $this->nowDatePlusOneYear;

            $subscriptionUuid = Uuid::uuid4()->toString();

            // add base subs to array
            $baseSubscription = [
                'uuid' => $subscriptionUuid,
                'product_uuid' => $productUuid,
                'customer_id' => $customerId,
                'domain' => $customerId . '-' . $i . '.nl',
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'next_billing_date' => $nextBillingDate,
                'billing_period' => 12,
                'contract_period' => 12,
                'gross_price' => 100,
                'net_price' => 80,
                // The end_date & next_billing_date will be set automatically by the SubscriptionObserver.
                'cancel_date' => null,
                'created_at' => $this->createdUpdatedDatetime,
                'updated_at' => $this->createdUpdatedDatetime,
            ];

            // make tech subs to array
            switch ($productGroupSlug) {
                case ProductGroupType::EXTENSION:
                    $domainsSubscriptions[] = [
                        'subscription_uuid' => $subscriptionUuid,
                        'provider_id' => $this->domainPlaceholderProviderId,
                        'created_at' => $this->createdUpdatedDatetime,
                        'updated_at' => $this->createdUpdatedDatetime,
                    ];

                    $baseSubscription['technical_status'] = DomainStatus::ACTIVE;
                    break;
                case ProductGroupType::HOSTING:
                    $hostingSubscriptions[] = [
                        'subscription_uuid' => $subscriptionUuid,
                        'provider_id' => $this->hostingPlaceholderProviderId,
                        'created_at' => $this->createdUpdatedDatetime,
                        'updated_at' => $this->createdUpdatedDatetime,
                    ];

                    $baseSubscription['technical_status'] = TechnicalStatus::OK->value;
                    break;
            }

            $baseSubscriptions[] = $baseSubscription;
        }

        return [
            $baseSubscriptions,
            $domainsSubscriptions,
            $hostingSubscriptions,
        ];
    }

    private function seedChildSubscriptions(): void
    {
        $childSubscriptionsAmount = $this->configuration->getAsInteger('bulk-seeder.child_subscriptions_amount');

        $this->output->writeln("generating $childSubscriptionsAmount child subscriptions...");

        $subscriptions = DB::select(
            query: Subscription::query()
                ->select([
                    'id',
                    'customer_id',
                    'domain',
                    'start_date',
                    'end_date',
                    'next_billing_date',
                    'billing_period',
                    'contract_period',
                ])
                ->whereRaw('product_uuid = ?')
                ->limit($childSubscriptionsAmount)
                ->toSql(),
            bindings: [
                $this->nlProduct->uuid,
            ],
        );

        $productUuid = $this->dnsProduct->uuid;

        $childSubscriptions = [];

        foreach ($subscriptions as $subscription) {
            $childSubscriptions[] = [
                'uuid' => Uuid::uuid4()->toString(),
                'product_uuid' => $productUuid,
                'customer_id' => $subscription->customer_id,
                'domain' => $subscription->domain,
                'administrative_status' => AdministrativeStatus::ACTIVE->value,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'next_billing_date' => $subscription->next_billing_date,
                'billing_period' => $subscription->billing_period,
                'contract_period' => $subscription->contract_period,
                'gross_price' => 0,
                'net_price' => 0,
                'parent_subscription_id' => $subscription->id,
                // The end_date & next_billing_date will be set automatically by the SubscriptionObserver.
                'cancel_date' => null,
                'created_at' => $this->createdUpdatedDatetime,
                'updated_at' => $this->createdUpdatedDatetime,
            ];
        }

        $this->output->writeln('starting child subscriptions insert...');

        foreach (array_chunk($childSubscriptions, 1000) as $childSubscriptionsInsertData) {
            DB::table('subscriptions')->insert($childSubscriptionsInsertData);
        }

        $this->output->writeln('finished child subscriptions insert');
    }

    private function insertFerryMigrationData(): void
    {
        $amountOfCustomersWithMigratedSubscriptions = $this->configuration->getAsInteger(
            'bulk-seeder.amount_of_customers_with_migrated_subscriptions',
        );

        $this->output->writeln("saving ferry data for $amountOfCustomersWithMigratedSubscriptions customers...");

        $customers = Customer::query()
            ->where('id', '>=', $this->customerIdStart)
            ->orderBy('id')
            ->limit($amountOfCustomersWithMigratedSubscriptions)
            ->cursor();

        /** @var Customer $customer */
        foreach ($customers as $customer) {
            /** @var MigratedCustomer $migratedCustomer */
            $migratedCustomer = $customer
                ->migratedCustomers()
                ->create([
                    'reference_customer_number' => 'reference_customer_number_' . $customer->id,
                    'reference_name' => 'reference_name_' . $customer->id,
                    'group_type' => 'bulk-seeder',
                ]);

            /** @var Subscription $subscription */
            foreach ($customer->subscriptions()->cursor() as $subscription) {
                /** @var MigratedSubscription $migratedSubscription */
                $migratedSubscription = $subscription
                    ->migratedSubscriptions()
                    ->create([
                        'reference_subscription_id' => 'reference_subscription_' . $subscription->id,
                        'reference_product_id' => 'reference_product_' . uniqid(),
                    ]);

                $migratedCustomer->migratedSubscriptions()->attach($migratedSubscription);
            }
        }

        $this->output->writeln('finished saving ferry data');
    }
}
