<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios;

use Carbon\CarbonImmutable;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Customers\Models\MigratedSubscription;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Enums\MigrationSubscriptionStatus;
use Waterfront\Domain\Ferry\Models\MigratedSubscriptionSteps;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ManualMigrationSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepository,
    ) {
    }

    public function run(): void
    {
        $customer = $this->customer();
        $migrationCustomer = $this->migratedCustomer($customer);
        $this->domainSubscription($customer->id, $migrationCustomer);
    }

    public function migratedCustomer(Customer $customer): MigratedCustomer
    {
        $migratedCustomer = new MigratedCustomer();
        $migratedCustomer->reference_customer_number = '12345';
        $migratedCustomer->reference_name = 'VIP';
        $migratedCustomer->group_type = 'manual_migration';
        $migratedCustomer->successful = false;
        $migratedCustomer->save();
        $migratedCustomer->customers()->attach($customer);

        return $migratedCustomer;
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Str::uuid();
        $customer->first_name = 'Manual Migration';
        $customer->last_name = 'Kees';
        $customer->email = 'manual.migration.kees@sandwave.io';
        $customer->organization = 'Sandwave.io';
        $customer->department = null;
        $customer->gender = Gender::MALE->value;
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '12345678';
        $customer->locale = Locale::DUTCH->value;
        $customer->payment_type = PaymentType::CREDIT;
        $customer->has_direct_debit = true;
        $customer->credit_limit = 5000000;
        $customer->terms_of_payment = 14;
        $customer->icp = false;
        $customer->vat_rate = 21.0;
        $customer->data_last_confirmed_at = CarbonImmutable::now();
        $customer->customer_since = CarbonImmutable::now();
        $customer->save();

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->save();

        $this->customerContact($customer);

        return $customer;
    }

    private function customerContact(Customer $customer): void
    {
        $contact = new CustomerContact();
        $contact->uuid = Str::uuid()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Manual Migration';
        $contact->last_name = 'Kees';
        $contact->email = 'test.kees@sandwave.io';
        $contact->type = CustomerContactType::DEFAULT->value;
        $contact->save();
    }

    private function domainSubscription(int $customerId, MigratedCustomer $migratedCustomer): void
    {
        $product = $this->referenceRepository->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepository->get(
            ProductReference::DOMAIN_NL_REGISTRATION_PRICE,
            ProductPriceComponent::class,
        );
        $provider = $this->referenceRepository->get(ProductReference::DOMAIN_PROVIDER_PLACEHOLDER, Provider::class);

        $dnsNameserver1 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepository->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $subscription = new Subscription();
        $subscription->uuid = Str::uuid()->toString();
        $subscription->customer_id = $customerId;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = 'ferry-manual-migration-placeholder-domain.test';
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = 0;
        $subscription->gross_price = 0;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $price->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        $deployment = new DomainDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->last_result = null;
        $deployment->last_result_received = null;
        $deployment->dnssec_enabled = false;
        $deployment->private_whois_enabled = false;
        $deployment->contact_owner_id = null;
        $deployment->save();

        $migratedSubscription = new MigratedSubscription();
        $migratedSubscription->reference_product_id = '12';
        $migratedSubscription->reference_subscription_id = 'existing_business_product_domain';
        $migratedSubscription->save();
        $migratedSubscription->subscriptions()->attach($subscription);
        $migratedSubscription->migratedCustomers()->attach($migratedCustomer);

        $dnsProduct = $this->referenceRepository->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepository->get(
            ProductReference::DNS_FREE_REGISTRATION_PRICE,
            ProductPriceComponent::class,
        );

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Str::uuid()->toString();
        $dnsSubscription->customer_id = $customerId;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = 'ferry-migration-placeholder-domain.test';
        $dnsSubscription->contract_period = $dnsPrice->contract_period;
        $dnsSubscription->billing_period = $dnsPrice->billing_period;
        $dnsSubscription->net_price = 0;
        $dnsSubscription->gross_price = 0;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = CarbonImmutable::yesterday();
        $dnsSubscription->next_billing_date = $dnsSubscription->start_date->addMonths($dnsSubscription->billing_period);
        $dnsSubscription->end_date = $dnsSubscription->start_date->addMonths($dnsSubscription->contract_period);
        $dnsSubscription->parent()->associate($subscription);
        $dnsSubscription->save();

        $dnsSubscriptionPrice = new SubscriptionPrice();
        $dnsSubscriptionPrice->subscription_id = $dnsSubscription->id;
        $dnsSubscriptionPrice->valid_from = $dnsSubscription->start_date;
        $dnsSubscriptionPrice->net_price = $dnsSubscription->net_price;
        $dnsSubscriptionPrice->save();

        $dnsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $dnsSubscriptionPriceComponent->subscription_price_id = $dnsSubscriptionPrice->id;
        $dnsSubscriptionPriceComponent->type = $dnsPrice->type;
        $dnsSubscriptionPriceComponent->percentage_discount = null;
        $dnsSubscriptionPriceComponent->fixed_discount = null;
        $dnsSubscriptionPriceComponent->fixed_price = $dnsSubscription->net_price;
        $dnsSubscriptionPriceComponent->new_price = $dnsSubscription->net_price;
        $dnsSubscriptionPriceComponent->order_applied = 1;
        $dnsSubscriptionPriceComponent->save();

        $dnsSubscription->subscription_price_id = $dnsSubscriptionPrice->id;
        $dnsSubscription->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment
            ->dnsNameservers()
            ->saveMany([
                $dnsNameserver1,
                $dnsNameserver2,
                $dnsNameserver3,
            ]);

        $this->makeManualMigrationSubscriptionSteps($subscription);
    }

    private function makeManualMigrationSubscriptionSteps(Subscription $subscription): void
    {
        $time = CarbonImmutable::yesterday();
        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::CUSTOMER;
        $step->status = MigrationSubscriptionStatus::EXECUTED;
        $step->executed_at = $time->addHour();
        $step->save();

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::DOMAIN_MIGRATION;
        $step->status = MigrationSubscriptionStatus::EXECUTED;
        $step->executed_at = $time->addHours(2);
        $step->save();

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::NAMESERVER;
        $step->status = MigrationSubscriptionStatus::EXECUTED;
        $step->executed_at = $time->addHours(3);
        $step->save();

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::CONFIGURE_DNS;
        $step->status = MigrationSubscriptionStatus::FAILED;
        $step->executed_at = $time->addHours(4);
        $step->save();

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::CONFIGURE_DNS;
        $step->status = MigrationSubscriptionStatus::EXECUTED;
        $step->executed_at = $time->addHours(5);
        $step->save();

        $step = new MigratedSubscriptionSteps();
        $step->subscription_id = $subscription->id;
        $step->step = MigrationStep::ENABLE_DNSSEC;
        $step->status = MigrationSubscriptionStatus::NOT_EXECUTED;
        $step->save();
    }
}
