<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Scenarios\TestKees;

use Carbon\CarbonImmutable;
use Database\Seeders\Platform\PlatformReference;
use Database\Seeders\Products\ProductReference;
use Database\Seeders\Scenarios\ScenarioReference;
use Database\Seeders\Support\ReferenceRepository;
use Illuminate\Database\Seeder;
use JsonException;
use Ramsey\Uuid\Uuid;
use SandwaveIo\HarborMessages\Message\DebtorInvoiceLines\InvoiceLine;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Customers\Enums\CustomerContactType;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\Locale;
use Waterfront\Domain\Customers\Enums\PaymentType;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Customers\Models\CustomerProductDiscount;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Models\DnsExternalNameserver;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Domain\DNS\Models\DnsVanityNameserver;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainContactAnonymousHandle;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Models\DomainProviderBusinessUnit;
use Waterfront\Domain\Domains\Models\DomainProviderStatus;
use Waterfront\Domain\Email\Enums\ReceiverType;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Experiment\Models\Experiment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Lighthouse\DTO\IdentityMetadataDTO;
use Waterfront\Domain\Marketing\Enums\HubspotObjectType;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventStatus;
use Waterfront\Domain\Marketing\Models\HubspotEvent;
use Waterfront\Domain\Marketing\Models\HubspotObjectSync;
use Waterfront\Domain\Microsoft365\Enums\CustomerInfoType;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365OrderStatus;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365HttpLog;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Models\OneTimeService;
use Waterfront\Domain\Orders\Enums\OrderLineItemStatus;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Payments\Enums\PaymentMethod;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Pricing\Models\SubscriptionPrice;
use Waterfront\Domain\Pricing\Models\SubscriptionPriceComponent;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductDiscount;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Provision\Backup\Acronis\Models\AcronisProvider;
use Waterfront\Domain\Provision\Backup\Models\AcronisBackupDeployment;
use Waterfront\Domain\Provision\Backup\Models\BackupDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Hosting\Models\DirectAdminHostingDeployment;
use Waterfront\Domain\Provision\Hosting\Models\HostingDeployment as ProvisioningHostingDeployment;
use Waterfront\Domain\Provision\Hosting\Models\PleskHostingDeployment;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Models\CaddyContext;
use Waterfront\Domain\Provision\Redirects\Models\CaddyRedirectDeployment;
use Waterfront\Domain\Provision\Redirects\Models\RedirectDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitSitebuilderDeployment;
use Waterfront\Domain\Provision\Sitebuilder\Models\SitebuilderDeployment;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackRequest;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\RetentionToolkit\Enums\CustomerType;
use Waterfront\Domain\RetentionToolkit\Enums\SelectedAction;
use Waterfront\Domain\RetentionToolkit\Models\CustomerRetentionOffer;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\SubscriptionChangeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Label;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionChange;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\SshKey;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\RtrClient\Enums\RtrResponseSource;
use Waterfront\Infra\RtrClient\Enums\SubjectStatusType;
use Waterfront\Infra\RtrClient\Models\RtrResponseLog;
use Webmozart\Assert\Assert;

class TestKeesSeeder extends Seeder
{
    public function __construct(
        private readonly ReferenceRepository $referenceRepo
    ) {
    }

    public function run(): void
    {
        $customer = $this->customer();

        $this->contacts($customer);
        $this->domainContacts($customer);
        $this->dnsTemplates($customer);

        $this->discounts($customer);
        $this->mollieMandates($customer);
        $this->wallet($customer);

        $this->emailHistory($customer);

        // Domain
        $this->domainNlWithPriceLadderExperiment($customer);
        $this->domainNLUnProcessedOrder($customer);
        $this->domainEu($customer);
        $this->domainBe($customer);
        $this->domainComWithPremiumDns($customer);
        $this->domainNlWithDnsRecordsChanges($customer);
        $this->domainNlWithExternalNameservers($customer);
        $this->domainNlWithArgewebBusinessUnitRtr($customer);
        $this->domainNlWithWaterfrontBusinessUnitRtr($customer);
        $this->domainFrWithTrusteeAddon($customer);
        $this->labels($customer);

        // VPS
        $this->managerDomainHaarlem($customer);
        $this->vpsUnlinkedSSHKey($customer);
        $this->managerDomainAmsterdam($customer);
        $this->vpsCloud2UbuntuSshHaarlem($customer);
        $this->vpsCloud5UbuntuSshHaarlem($customer);
        $this->vpsCloud10UbuntuHaarlem($customer);
        $this->vpsCloud20UbuntuSshAmsterdam($customer);

        // Sitebuilder
        $this->sitebuilder($customer);
        $this->provisionSitebuilderBasekit($customer);
        $this->provisionSitebuilderBasekitWithAddons($customer);
        $this->provisionSitebuilderWebShopBasekitWithAddons($customer);
        $this->provisionSitebuilderWebshop($customer);

        // Hosting
        $this->webBasic($customer);
        $this->webBasicWpWithOneTimeService($customer); // One time service
        $this->webBasicWpWithUnprocessedOneTimeService($customer);
        $this->webBasicExpired($customer);
        $this->webGrow($customer);
        $this->webStart($customer);
        $this->webPlus($customer);
        $this->redirect($customer);
        $this->caddyRedirect($customer);
        $this->hostingPlaceholder($customer);
        $this->bronze($customer);
        $this->premium($customer);
        $this->mailOnly($customer);
        $this->wordpressToolkit($customer);
        $this->provisioningHostingDirectAdmin($customer);
        $this->provisioningHostingPlesk($customer);

        // Hosting mail-only
        $this->mailOnlyBasicDirectAdmin($customer);
        $this->mailOnlyGrowPlesk($customer);
        $this->mailOnlyStartPlesk($customer);
        $this->mailOnlyPlusPlesk($customer);

        // Hosting web-only
        $this->webOnlyMini($customer);
        $this->webOnlyBasic($customer);
        $this->webOnlyBasicWp($customer);
        $this->webOnlyGrow($customer);
        $this->webOnlyStart($customer);
        $this->webOnlyPlus($customer);

        // SSL
        $this->singleDomain($customer);
        $this->wildcard($customer);
        $this->extendedValidation($customer);
        $this->sslPlaceholder($customer);
        $this->sslOnlyFromMigrations($customer);

        // M365
        $this->appsForBusiness($customer);

        // Reseller hosting
        $this->resellerBrons($customer);
        $this->resellerSilver($customer);
        $this->resellerGold($customer);

        //Redirect
        $this->paidRedirect($customer);

        // Puzzel
        $this->puzzelBlockedDates();
        $this->puzzelCallbackRequests($customer);

        // Retention
        $this->customerRetentionOffers($customer);

        // Acronis
        $this->provisionBackupAcronis($customer);

        // Subscription changes
        $this->subscriptionChanges($customer);
    }

    private function customer(): Customer
    {
        $customer = new Customer();
        $customer->uuid = Uuid::uuid4();
        $customer->first_name = 'Test';
        $customer->last_name = 'Kees';
        $customer->email = 'test.kees@sandwave.io';
        $customer->organization = 'Sandwave.io';
        $customer->department = 'Lean & Synergistic Management Solutions';
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
        $customer->refresh();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES, $customer);

        $audit = new Audit();
        $audit->user_id = $customer->id;
        $audit->identity_uuid = $customer->uuid;
        $audit->auditable_type = Customer::class;
        $audit->auditable_id = $customer->id;
        $audit->event = 'created';
        $audit->old_values = [];
        // Normally new_values should contain much more, but for readability reasons it's kept succinct.
        $audit->new_values = [
            'first_name' => 'Test',
            'last_name' => 'Kees',
            'icp' => false,
            'vat_rate' => 21.0,
        ];
        $audit->identity_metadata = "{\"email\": \"$customer->email\", \"foo\": \"bar\"}";
        $audit->save();

        $address = new CustomerAddress();
        $address->customer_id = $customer->id;
        $address->street_name = 'Edisonweg';
        $address->street_number = '51';
        $address->street_number_addition = 'D';
        $address->zip_code = '4388 AG';
        $address->city = 'Vlissingen';
        $address->country_code = 'NL';
        $address->save();

        return $customer;
    }

    private function contacts(Customer $customer): void
    {
        $contact = new CustomerContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Test';
        $contact->last_name = 'Kees';
        $contact->email = 'test.kees@sandwave.io';
        $contact->type = CustomerContactType::DEFAULT->value;
        $contact->save();

        $contact = new CustomerContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->customer_id = $customer->id;
        $contact->first_name = 'Financial';
        $contact->last_name = 'Contact';
        $contact->email = 'financial@sandwave.io';
        $contact->type = CustomerContactType::FINANCIAL->value;
        $contact->save();
    }

    private function domainContacts(Customer $customer): void
    {
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);

        $contact = new DomainContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->email = 'fake@faker.nl';
        $contact->first_name = 'First';
        $contact->last_name = 'Last';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '450647';
        $contact->street_name = 'Fakestreet';
        $contact->street_number = '1';
        $contact->zip_code = '1234 AB';
        $contact->city = 'Fakecity';
        $contact->country_code = 'NL';
        $contact->organization = 'Sandwave';
        $contact->default_owner = true;
        $contact->customer_id = $customer->id;
        $contact->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, $contact);

        $contact->providers()->attach($provider, [
            'external_contact' => 123,
        ]);

        $contact = new DomainContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->email = 'fake@faker.nl';
        $contact->first_name = 'Club';
        $contact->last_name = 'House';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '123456';
        $contact->street_name = 'Clubhousestreet';
        $contact->street_number = '1';
        $contact->zip_code = '5678 AB';
        $contact->city = 'City';
        $contact->country_code = 'NL';
        $contact->default_owner = false;
        $contact->customer_id = $customer->id;
        $contact->save();

        $contact->providers()->attach($provider, [
            'external_contact' => 456,
        ]);

        $contact = new DomainContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->email = 'fake@anonymous.nl';
        $contact->first_name = 'First';
        $contact->last_name = 'Last';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '450647';
        $contact->street_name = 'Fakestreet';
        $contact->street_number = '1';
        $contact->zip_code = '1234 AB';
        $contact->city = 'Fakecity';
        $contact->country_code = 'NL';
        $contact->organization = 'Completely Anonymized';
        $contact->default_owner = false;
        $contact->customer_id = $customer->id;
        $contact->save();

        $anonymousHandle = new DomainContactAnonymousHandle();
        $anonymousHandle->handle = 'an-anonymous-handle-123';
        $anonymousHandle->original_business_unit = 'example_business_unit_for_historical_reference';
        $anonymousHandle->save();

        $contact->providers()->attach($provider, [
            'external_contact' => $anonymousHandle->handle,
        ]);

        $contact = new DomainContact();
        $contact->uuid = Uuid::uuid4()->toString();
        $contact->email = 'fake@faker.nl';
        $contact->first_name = 'Argeweb';
        $contact->last_name = 'BV';
        $contact->phone_country_code = '+31';
        $contact->phone_area_code = '115';
        $contact->phone_subscriber_number = '123456';
        $contact->street_name = 'Fakerstreet';
        $contact->street_number = '1';
        $contact->zip_code = '5678 AB';
        $contact->city = 'City';
        $contact->country_code = 'NL';
        $contact->organization = 'Argeweb';
        $contact->default_owner = false;
        $contact->customer_id = $customer->id;
        $contact->save();

        $argewebBusinessUnit = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_ARGEWEB, DomainProviderBusinessUnit::class);
        $contact->providers()->attach($provider, [
            'external_contact' => 'ARGEWEB-HANDLE',
            'domain_business_unit_id' => $argewebBusinessUnit->id,
        ]);
    }

    private function discounts(Customer $customer): void
    {
        $productDiscount = $this->referenceRepo->get(ProductReference::DOMAIN_RESELLER_DISCOUNT, ProductDiscount::class);

        $discount = new CustomerProductDiscount();
        $discount->customer_id = $customer->id;
        $discount->product_discount_id = $productDiscount->id;
        $discount->save();
    }

    private function mollieMandates(Customer $customer): void
    {
        $mollieCustomer = new MollieCustomer();
        $mollieCustomer->mollie_customer_reference_id = 'cst_12345';
        $mollieCustomer->customer_id = $customer->id;
        $mollieCustomer->save();

        $mandate = new Mandate();
        $mandate->mollie_mandate_reference_id = 'mdt_directdebitref';
        $mandate->method = MollieMandateMethod::DIRECTDEBIT;
        $mandate->signature_date = CarbonImmutable::yesterday();
        $mandate->mollie_customer_id = $mollieCustomer->id;
        $mandate->save();

        $mandate = new Mandate();
        $mandate->mollie_mandate_reference_id = 'mdt_paypalref';
        $mandate->method = MollieMandateMethod::PAYPAL;
        $mandate->signature_date = CarbonImmutable::today();
        $mandate->mollie_customer_id = $mollieCustomer->id;
        $mandate->save();
    }

    private function wallet(Customer $customer): void
    {
        $wallet = new CustomerWallet();
        $wallet->customer_id = $customer->id;
        $wallet->amount = 5050;
        $wallet->save();
    }

    private function dnsTemplates(Customer $customer): void
    {
        $template = new DnsCustomerTemplate();
        $template->name = 'testTemplate';
        $template->customer_id = $customer->id;
        $template->save();

        $record = new DnsCustomerTemplateRecord();
        $record->name = 'test.@';
        $record->content = '127.0.0.1';
        $record->type = DnsRecordType::A->value;
        $record->ttl = 3600;
        $record->template_id = $template->id;
        $record->save();

        $record = new DnsCustomerTemplateRecord();
        $record->name = 'test.@';
        $record->content = '::1';
        $record->type = DnsRecordType::AAAA->value;
        $record->ttl = 600;
        $record->template_id = $template->id;
        $record->save();

        $record = new DnsCustomerTemplateRecord();
        $record->name = 'test_allot.@';
        $record->content = 'test.@';
        $record->type = DnsRecordType::CNAME->value;
        $record->ttl = 3600;
        $record->template_id = $template->id;
        $record->save();
    }

    private function domainNlWithPriceLadderExperiment(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);
        $priceLadderExperiment = $this->referenceRepo->get(PlatformReference::EXPERIMENT_PRICE_LADDER, Experiment::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-legacy-dns.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $priceLadderExperiment->subscriptions()->save($domainSubscription);

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_NL_SUBSCRIPTION, $domainSubscription);

        $hubspotCustomerEventSuccess = new HubspotEvent();
        $hubspotCustomerEventSuccess->customer_id = $customer->id;
        $hubspotCustomerEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotCustomerEventSuccess->message = 'Created new Hubspot contact';
        $hubspotCustomerEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotCustomerEventSuccess->save();

        $hubspotObjectSync = new HubspotObjectSync();
        $hubspotObjectSync->sandwave_object_id = $customer->uuid;
        $hubspotObjectSync->sandwave_object_type = HubspotObjectType::CUSTOMER;
        $hubspotObjectSync->hubspot_object_id = $customer->uuid->toString() . '-hubspot';
        $hubspotObjectSync->synced_at = CarbonImmutable::now();
        $hubspotObjectSync->save();

        $hubspotSubscriptionEventSuccess = new HubspotEvent();
        $hubspotSubscriptionEventSuccess->customer_id = $customer->id;
        $hubspotSubscriptionEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotSubscriptionEventSuccess->message = 'Created new Hubspot subscription';
        $hubspotSubscriptionEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotSubscriptionEventSuccess->save();

        $hubspotOneTimeServiceEventSuccess = new HubspotEvent();
        $hubspotOneTimeServiceEventSuccess->customer_id = $customer->id;
        $hubspotOneTimeServiceEventSuccess->event = 'Synchronizing one time service to Hubspot';
        $hubspotOneTimeServiceEventSuccess->message = 'Created new Hubspot one time service';
        $hubspotOneTimeServiceEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotOneTimeServiceEventSuccess->save();

        $hubspotObjectSync = new HubspotObjectSync();
        $hubspotObjectSync->sandwave_object_id = Uuid::fromString($domainSubscription->uuid);
        $hubspotObjectSync->sandwave_object_type = HubspotObjectType::SUBSCRIPTION;
        $hubspotObjectSync->hubspot_object_id = Uuid::fromString($domainSubscription->uuid) . '-hubspot';
        $hubspotObjectSync->synced_at = CarbonImmutable::now();
        $hubspotObjectSync->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function domainNLUnProcessedOrder(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::ON_HOLD;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-legacy-xd-dns.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function domainNlWithExternalNameservers(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-external-nameservers.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_NL_SUBSCRIPTION_EXTERNAL_NAMESERVERS, $domainSubscription);

        $hubspotCustomerEventSuccess = new HubspotEvent();
        $hubspotCustomerEventSuccess->customer_id = $customer->id;
        $hubspotCustomerEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotCustomerEventSuccess->message = 'Created new Hubspot contact';
        $hubspotCustomerEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotCustomerEventSuccess->save();

        $hubspotSubscriptionEventSuccess = new HubspotEvent();
        $hubspotSubscriptionEventSuccess->customer_id = $customer->id;
        $hubspotSubscriptionEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotSubscriptionEventSuccess->message = 'Created new Hubspot subscription';
        $hubspotSubscriptionEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotSubscriptionEventSuccess->save();

        $hubspotOneTimeServiceEventSuccess = new HubspotEvent();
        $hubspotOneTimeServiceEventSuccess->customer_id = $customer->id;
        $hubspotOneTimeServiceEventSuccess->event = 'Synchronizing one time service to Hubspot';
        $hubspotOneTimeServiceEventSuccess->message = 'Created new Hubspot one time service';
        $hubspotOneTimeServiceEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotOneTimeServiceEventSuccess->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $dnsSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::EXTERNAL;
        $dnsDeployment->save();

        $externalDnsNameserver1 = new DnsExternalNameserver();
        $externalDnsNameserver1->dns_deployment_id = $dnsDeployment->id;
        $externalDnsNameserver1->nameserver = 'ns1.external.nl';
        $externalDnsNameserver1->save();

        $externalDnsNameserver2 = new DnsExternalNameserver();
        $externalDnsNameserver2->dns_deployment_id = $dnsDeployment->id;
        $externalDnsNameserver2->nameserver = 'ns2.external.nl';
        $externalDnsNameserver2->save();

        $externalDnsNameserver3 = new DnsExternalNameserver();
        $externalDnsNameserver3->dns_deployment_id = $dnsDeployment->id;
        $externalDnsNameserver3->nameserver = 'ns3.external.nl';
        $externalDnsNameserver3->save();

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    /**
     * @throws JsonException
     */
    private function domainNlWithDnsRecordsChanges(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependencies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-legacy-dns-record-changes.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();

        $dnsRecordChange = new DnsRecordChange();
        $dnsRecordChange->record_type = DnsRecordType::A;
        $dnsRecordChange->change_type = DnsChangeType::CREATED;
        $dnsRecordChange->agent_type = DnsAgentType::CUSTOMER;
        $dnsRecordChange->name = $dnsSubscription->domain;
        $dnsRecordChange->content = '192.128.0.1';
        $dnsRecordChange->ttl = 3600;
        $dnsRecordChange->subscription_id = $dnsSubscription->id;
        $dnsRecordChange->ip_address = '::1';
        $dnsRecordChange->changed_by_uuid = $customer->uuid;
        $dnsRecordChange->changed_by_metadata = json_encode(['email' => $customer->email, 'schemaId' => SchemaId::CUSTOMER], JSON_THROW_ON_ERROR);
        $dnsRecordChange->save();

        $dnsRecordChange = new DnsRecordChange();
        $dnsRecordChange->record_type = DnsRecordType::AAAA;
        $dnsRecordChange->change_type = DnsChangeType::CREATED;
        $dnsRecordChange->agent_type = DnsAgentType::CUSTOMER;
        $dnsRecordChange->name = $dnsSubscription->domain;
        $dnsRecordChange->content = '::1';
        $dnsRecordChange->ttl = 3600;
        $dnsRecordChange->subscription_id = $dnsSubscription->id;
        $dnsRecordChange->ip_address = '192.128.0.1';
        $dnsRecordChange->changed_by_uuid = $customer->uuid;
        $dnsRecordChange->changed_by_metadata = json_encode(['email' => $customer->email, 'schemaId' => SchemaId::CUSTOMER], JSON_THROW_ON_ERROR);
        $dnsRecordChange->save();

        $dnsRecordChange = new DnsRecordChange();
        $dnsRecordChange->record_type = DnsRecordType::MX;
        $dnsRecordChange->change_type = DnsChangeType::CREATED;
        $dnsRecordChange->agent_type = DnsAgentType::CUSTOMER;
        $dnsRecordChange->name = $dnsSubscription->domain;
        $dnsRecordChange->content = sprintf('mail.%s', $dnsSubscription->domain);
        $dnsRecordChange->ttl = 3600;
        $dnsRecordChange->subscription_id = $dnsSubscription->id;
        $dnsRecordChange->ip_address = '192.128.0.1';
        $dnsRecordChange->changed_by_uuid = $customer->uuid;
        $dnsRecordChange->changed_by_metadata = json_encode(['email' => $customer->email, 'schemaId' => SchemaId::CUSTOMER], JSON_THROW_ON_ERROR);
        $dnsRecordChange->save();

        $dnsRecordChange = new DnsRecordChange();
        $dnsRecordChange->record_type = DnsRecordType::TXT;
        $dnsRecordChange->change_type = DnsChangeType::CREATED;
        $dnsRecordChange->agent_type = DnsAgentType::CUSTOMER;
        $dnsRecordChange->name = sprintf('_dmarc.%s', $dnsSubscription->domain);
        $dnsRecordChange->content = sprintf('"v=DMARC1; p=none; rua=mailto:dmarc@%s"', $dnsSubscription->domain);
        $dnsRecordChange->ttl = 3600;
        $dnsRecordChange->subscription_id = $dnsSubscription->id;
        $dnsRecordChange->ip_address = '192.128.0.1';
        $dnsRecordChange->changed_by_uuid = $customer->uuid;
        $dnsRecordChange->changed_by_metadata = json_encode(['email' => $customer->email, 'schemaId' => SchemaId::CUSTOMER], JSON_THROW_ON_ERROR);
        $dnsRecordChange->save();

        $dnsRecordChange = new DnsRecordChange();
        $dnsRecordChange->record_type = DnsRecordType::TXT;
        $dnsRecordChange->change_type = DnsChangeType::CREATED;
        $dnsRecordChange->agent_type = DnsAgentType::CUSTOMER;
        $dnsRecordChange->name = $dnsSubscription->domain;
        $dnsRecordChange->content = sprintf('"v=spf1 include:_spf.%s ~all"', $dnsSubscription->domain);
        $dnsRecordChange->ttl = 3600;
        $dnsRecordChange->subscription_id = $dnsSubscription->id;
        $dnsRecordChange->ip_address = '192.128.0.1';
        $dnsRecordChange->changed_by_uuid = $customer->uuid;
        $dnsRecordChange->changed_by_metadata = json_encode(['email' => $customer->email, 'schemaId' => SchemaId::CUSTOMER], JSON_THROW_ON_ERROR);
        $dnsRecordChange->save();
    }

    private function domainEu(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_EU, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_EU_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-legacy-dns.eu';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_EU_SUBSCRIPTION, $domainSubscription);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::API_CALL;
        $rtrResponseLog->response = '{"message": "This is an unknown error", "type": "ValidationError"}';
        $rtrResponseLog->rtr_notification_id = 1337;
        $rtrResponseLog->processed_at = CarbonImmutable::now();
        $rtrResponseLog->failed_at = CarbonImmutable::now();
        $rtrResponseLog->save();

        $domainProviderStatus = new DomainProviderStatus();
        $domainProviderStatus->rtr_response_log_id = $rtrResponseLog->id;
        $domainProviderStatus->domain_deployment_id = $domainDeployment->id;
        $domainProviderStatus->provider_id = $domainDeployment->provider->id;
        $domainProviderStatus->status = SubjectStatusType::FAILED->value;
        $domainProviderStatus->received_result = 'failed';
        $domainProviderStatus->save();

        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::NOTIFICATION;
        $rtrResponseLog->response = '{"message": "ok"}';
        $rtrResponseLog->rtr_notification_id = 1338;
        $rtrResponseLog->processed_at = CarbonImmutable::now();
        $rtrResponseLog->save();

        $domainProviderStatus = new DomainProviderStatus();
        $domainProviderStatus->rtr_response_log_id = $rtrResponseLog->id;
        $domainProviderStatus->domain_deployment_id = $domainDeployment->id;
        $domainProviderStatus->provider_id = $domainDeployment->provider->id;
        $domainProviderStatus->status = SubjectStatusType::OK->value;
        $domainProviderStatus->received_result = 'ok';
        $domainProviderStatus->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();

        $audit = new Audit();
        $audit->user_id = $customer->id;
        $audit->identity_uuid = $customer->uuid;
        $audit->auditable_type = Invoice::class;
        $audit->auditable_id = $dnsInvoiceItem->id;
        $audit->event = 'created';
        $audit->old_values = [];
        // Normally new_values should contain much more, but for readability reasons it's kept succinct.
        $audit->new_values = [
            'customer_number' => $customer->customer_number,
            'domain' => $dnsSubscription->domain,
        ];
        $audit->identity_metadata = "{\"email\": \"$customer->email\", \"foo\": \"bar\"}";
        $audit->save();

        $oneTimeService = new OneTimeService();
        $oneTimeService->uuid = Uuid::uuid4();
        $oneTimeService->customer_id = $customer->id;
        $oneTimeService->subscription_id = $domainSubscription->id;
        $oneTimeService->product_id = $product->id;
        $oneTimeService->amount = 1;
        $oneTimeService->discount_percentage = 20;
        $oneTimeService->gross_price = 800;
        $oneTimeService->execution_date = CarbonImmutable::yesterday();
        $oneTimeService->status = OneTimeServiceStatus::OPEN;
        $oneTimeService->save();
    }

    private function domainBe(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_BE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_BE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependencies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-legacy-dns.be';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $audit = new Audit();
        $audit->user_id = $customer->id;
        $audit->identity_uuid = $customer->uuid;
        $audit->auditable_type = OrderLineItem::class;
        $audit->auditable_id = $domainOrderItem->id;
        $audit->event = 'created';
        $audit->old_values = [];
        // Normally new_values should contain much more, but for readability reasons it's kept succinct.
        $audit->new_values = [
            'order_id' => $order->id,
            'domain' => $domainOrderItem->domain,
            'product_name' => $domainOrderItem->product_name,
            'gross_price' => $domainOrderItem->gross_price,
        ];
        $audit->identity_metadata = "{\"email\": \"$customer->email\", \"foo\": \"bar\"}";
        $audit->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_BE_SUBSCRIPTION, $domainSubscription);

        $audit = new Audit();
        $audit->user_id = $customer->id;
        $audit->identity_uuid = $customer->uuid;
        $audit->auditable_type = Subscription::class;
        $audit->auditable_id = $domainSubscription->id;
        $audit->event = 'created';
        $audit->old_values = [];
        $audit->new_values = [
            'domain' => $domainSubscription->domain,
            'end_date' => $domainSubscription->end_date,
            'start_date' => $domainSubscription->start_date,
        ];
        $audit->identity_metadata = "{\"email\": \"$customer->email\", \"foo\": \"bar\"}";
        $audit->save();

        $hubspotEventFailed = new HubspotEvent();
        $hubspotEventFailed->customer_id = $customer->id;
        $hubspotEventFailed->event = 'Synchronizing subscription to Hubspot';
        $hubspotEventFailed->message = 'CRM Error: fake error';
        $hubspotEventFailed->status = HubspotEventStatus::FAILED;
        $hubspotEventFailed->save();

        $oneTimeService = new OneTimeService();
        $oneTimeService->uuid = Uuid::uuid4();
        $oneTimeService->customer_id = $customer->id;
        $oneTimeService->subscription_id = $domainSubscription->id;
        $oneTimeService->product_id = $product->id;
        $oneTimeService->amount = 1;
        $oneTimeService->discount_percentage = 30;
        $oneTimeService->gross_price = 900;
        $oneTimeService->execution_date = CarbonImmutable::yesterday();
        $oneTimeService->status = OneTimeServiceStatus::DONE;
        $oneTimeService->save();

        $invoice = new Invoice();
        $invoice->subscription_id = $domainSubscription->id;
        $invoice->customer_id = $customer->id;
        $invoice->product_id = $product->id;
        $invoice->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoice->vat_rate = $customer->vat_rate ?? 0;
        $invoice->ledger_code = $product->productGroup->ledger_code;
        $invoice->paid = false;
        $invoice->start_date = $oneTimeService->execution_date;
        $invoice->end_date = $oneTimeService->execution_date;
        $invoice->period = 0;
        $invoice->gross_price = $oneTimeService->gross_price;
        $invoice->net_price = (int) round($oneTimeService->gross_price * (1 - ($oneTimeService->discount_percentage * 0.01)));
        $invoice->title = $product->name;
        $invoice->description = $product->name;
        $invoice->group_label = null;
        $invoice->type = InvoiceLine::TYPE_DEFAULT;
        $invoice->prepaid_reference = null;
        $invoice->save();

        $oneTimeService->invoices()->attach($invoice->id);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $audit = new Audit();
        $audit->user_id = $customer->id;
        $audit->identity_uuid = $customer->uuid;
        $audit->auditable_type = OrderLineItem::class;
        $audit->auditable_id = $domainOrderItem->id;
        $audit->event = 'updated';
        $audit->old_values = [
            'order_id' => $order->id,
            'domain' => $domainOrderItem->domain,
            'product_name' => $domainOrderItem->product_name,
            'gross_price' => $domainOrderItem->gross_price,
        ];
        $audit->new_values = [
            'subscription_uuid' => $domainOrderItem->subscription_uuid,
        ];
        $audit->identity_metadata = "{\"email\": \"$customer->email\", \"foo\": \"bar\"}";
        $audit->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function domainComWithPremiumDns(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_COM, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_COM_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependencies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_PREMIUM, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_PREMIUM_REGISTRATION_PRICE, ProductPriceComponent::class);
        $vanityNameServer1 = $this->referenceRepo->get(ProductReference::DNS_VANITY_NAMESERVER_1, DnsVanityNameserver::class);
        $vanityNameServer2 = $this->referenceRepo->get(ProductReference::DNS_VANITY_NAMESERVER_2, DnsVanityNameserver::class);
        $vanityNameServer3 = $this->referenceRepo->get(ProductReference::DNS_VANITY_NAMESERVER_3, DnsVanityNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-premium-dns.com';
        $domainOrderItem->transfer_secret = 'abcdefgh';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_DOMAIN_COM_SUBSCRIPTION, $domainSubscription);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->transfer_secret = $domainOrderItem->transfer_secret;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::API_CALL;
        $rtrResponseLog->response = '{"message": "Transfer is not possible for a domain with statuses \'[CLIENT_TRANSFER_PROHIBITED, OK]\'", "type": "ValidationError"}';
        $rtrResponseLog->rtr_notification_id = 1339;
        $rtrResponseLog->processed_at = CarbonImmutable::now();
        $rtrResponseLog->failed_at = CarbonImmutable::now();
        $rtrResponseLog->save();

        $domainProviderStatus = new DomainProviderStatus();
        $domainProviderStatus->rtr_response_log_id = $rtrResponseLog->id;
        $domainProviderStatus->domain_deployment_id = $domainDeployment->id;
        $domainProviderStatus->provider_id = $domainDeployment->provider->id;
        $domainProviderStatus->status = SubjectStatusType::FAILED->value;
        $domainProviderStatus->received_result = 'failed';
        $domainProviderStatus->save();

        $rtrResponseLog = new RtrResponseLog();
        $rtrResponseLog->source = RtrResponseSource::NOTIFICATION;
        $rtrResponseLog->response = '{"message": "ok"}';
        $rtrResponseLog->rtr_notification_id = 1340;
        $rtrResponseLog->processed_at = CarbonImmutable::now();
        $rtrResponseLog->save();

        $domainProviderStatus = new DomainProviderStatus();
        $domainProviderStatus->rtr_response_log_id = $rtrResponseLog->id;
        $domainProviderStatus->domain_deployment_id = $domainDeployment->id;
        $domainProviderStatus->provider_id = $domainDeployment->provider->id;
        $domainProviderStatus->status = SubjectStatusType::OK->value;
        $domainProviderStatus->received_result = 'ok';
        $domainProviderStatus->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::VANITY;
        $dnsDeployment->save();

        $dnsDeployment->vanityNameservers()->saveMany([
            $vanityNameServer1,
            $vanityNameServer2,
            $vanityNameServer3,
        ]);

        $mutation = new SubscriptionMutation();
        $mutation->subscription_id = $dnsSubscription->id;
        $mutation->product_id = $dnsProduct->id;
        $mutation->gross_price = 100;
        $mutation->net_price = 100;
        $mutation->billing_period = $dnsPrice->billing_period;
        $mutation->contract_period = $dnsPrice->contract_period;
        $mutation->mutated_at = CarbonImmutable::now();
        $mutation->save();

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function labels(Customer $customer): void
    {
        $nlDomainSubscription = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_NL_SUBSCRIPTION, Subscription::class);
        $euDomainSubscription = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_EU_SUBSCRIPTION, Subscription::class);
        $beDomainSubscription = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_BE_SUBSCRIPTION, Subscription::class);
        $comDomainSubscription = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_COM_SUBSCRIPTION, Subscription::class);

        $label = new Label();
        $label->value = 'webshop';
        $label->customer_id = $customer->id;
        $label->save();
        $label->subscriptions()->saveMany(
            [
                $nlDomainSubscription,
                $euDomainSubscription,
            ]
        );

        $label = new Label();
        $label->value = 'marketing-campagne';
        $label->customer_id = $customer->id;
        $label->save();
        $label->subscriptions()->saveMany(
            [
                $beDomainSubscription,
                $comDomainSubscription,
            ]
        );
    }

    private function managerDomainHaarlem(Customer $customer): void
    {
        // Manager domain dependencies
        $environment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_HAARLEM, Environment::class);

        $managerDomainDeployment = new ManagerDomainDeployment();
        $managerDomainDeployment->customer_id = $customer->id;
        $managerDomainDeployment->domain_id = '33cee36a-4860-4822-91d6-a3db342c39de';
        $managerDomainDeployment->domain_name = 'cs1635151549';
        $managerDomainDeployment->account = 'cs1635151549';
        $managerDomainDeployment->username = 'cs1635151549';
        $managerDomainDeployment->last_subscription_sync_at = CarbonImmutable::now()->subHour();
        $managerDomainDeployment->environment_id = $environment->id;
        $managerDomainDeployment->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_HAARLEM, $managerDomainDeployment);
    }

    private function vpsUnlinkedSSHKey(Customer $customer): void
    {
        $publicKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC+h9Y3ZvwRvRzZYIH1m4uqAF4gBH3AsPFvw1S4Gt+gWR5H50VFUFnFfrJP98kzSxg6NaWUcNe1OZUDNNuKqlzHum/JM7+lA3j5bmaatqz9h4RN83Ws9HPiZF1pVKFWRe29d0RVucAXlkQV1vuXx15IXDWYY3gydiDPaiyr5QkKpd9xG+SxTYB3IayyiBLWVH5ewUX3jFkGhLKm7ucJlbVCWJu0ZBcSoGIGlmU0aasCeoxIjq12TLoBlwvAlumJqKTZ+USeGWd+OPbyssoHjubep1EgN0REIzamIvJVSoA5gu4GGn1QNv1n4TQ+dtvh1hFFwHoPZpGj9ffaVnp3Pc4h some-random-key-unused';
        $sshKey = new SshKey();
        $sshKey->uuid = Uuid::uuid4();
        $sshKey->customer_id = $customer->id;
        $sshKey->key_name = 'ssh-key-vps-unused';
        $sshKey->public_key = $publicKey;
        $sshKey->fingerprint = '60:6E:5F:A3:56:3F:0B:3E:87:3E:DC:3A:AA:72:FC:3A:08:0B:48:37';
        $sshKey->cloudstack_ssh_name = sha1($publicKey);
        $sshKey->save();
    }

    private function managerDomainAmsterdam(Customer $customer): void
    {
        $environment = $this->referenceRepo->get(ProductReference::VPS_ENVIRONMENT_AMSTERDAM, Environment::class);

        $managerDomainDeployment = new ManagerDomainDeployment();
        $managerDomainDeployment->customer_id = $customer->id;
        $managerDomainDeployment->domain_id = '4533178e-8d27-4a8e-be7a-0c0dda14a8ac';
        $managerDomainDeployment->domain_name = 'cs42697363';
        $managerDomainDeployment->account = 'cs42697363';
        $managerDomainDeployment->username = 'cs42697363';
        $managerDomainDeployment->last_subscription_sync_at = CarbonImmutable::now()->subHour();
        $managerDomainDeployment->environment_id = $environment->id;
        $managerDomainDeployment->save();
        $this->referenceRepo->set(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_AMSTERDAM, $managerDomainDeployment);
    }

    private function vpsCloud2UbuntuSshHaarlem(Customer $customer): void
    {
        $vpsProduct = $this->referenceRepo->get(ProductReference::VPS_CLOUD_2, Product::class);
        $vpsPrice = $this->referenceRepo->get(ProductReference::VPS_CLOUD_2_REGISTRATION_PRICE, ProductPriceComponent::class);

        $managerDomainDeployment = $this->referenceRepo->get(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_HAARLEM, ManagerDomainDeployment::class);

        // OS dependencies
        $osProduct = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_SSH_REQUIRED, Product::class);
        $osPrice = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->total_price = $vpsPrice->price + $osPrice->price;
        $order->administration_fees = 0;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = false;
        $order->save();

        // OS seeding
        $osOrderItem = new OrderLineItem();
        $osOrderItem->order_id = $order->id;
        $osOrderItem->product_uuid = $osProduct->uuid;
        $osOrderItem->product_name = $osProduct->name;
        $osOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $osOrderItem->gross_price = $osPrice->price;
        $osOrderItem->net_price = $osPrice->price;
        $osOrderItem->billing_period = $osPrice->billing_period;
        $osOrderItem->contract_period = $osPrice->contract_period;
        $osOrderItem->should_invoice = true;
        $osOrderItem->save();

        $osSubscription = new Subscription();
        $osSubscription->uuid = Uuid::uuid4()->toString();
        $osSubscription->customer_id = $customer->id;
        $osSubscription->product_uuid = $osProduct->uuid;
        $osSubscription->contract_period = $osPrice->contract_period;
        $osSubscription->billing_period = $osPrice->billing_period;
        $osSubscription->net_price = $osOrderItem->net_price;
        $osSubscription->gross_price = $osOrderItem->gross_price;
        $osSubscription->technical_status = TechnicalStatus::OK->value;
        $osSubscription->start_date = CarbonImmutable::yesterday();
        $osSubscription->next_billing_date = $osSubscription->start_date->addMonths($osSubscription->billing_period);
        $osSubscription->end_date = $osSubscription->start_date->addMonths($osSubscription->contract_period);
        $osSubscription->save();

        $osSubscriptionPrice = new SubscriptionPrice();
        $osSubscriptionPrice->subscription_id = $osSubscription->id;
        $osSubscriptionPrice->valid_from = $osSubscription->start_date;
        $osSubscriptionPrice->net_price = $osSubscription->net_price;
        $osSubscriptionPrice->save();

        $osSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $osSubscriptionPriceComponent->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscriptionPriceComponent->type = $osPrice->type;
        $osSubscriptionPriceComponent->percentage_discount = null;
        $osSubscriptionPriceComponent->fixed_discount = null;
        $osSubscriptionPriceComponent->fixed_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->new_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->order_applied = 1;
        $osSubscriptionPriceComponent->save();

        $osSubscription->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscription->save();

        $osOrderItem->subscription_uuid = $osSubscription->uuid;
        $osOrderItem->save();

        // VPS seeding
        $vpsOrderItem = new OrderLineItem();
        $vpsOrderItem->order_id = $order->id;
        $vpsOrderItem->product_uuid = $vpsProduct->uuid;
        $vpsOrderItem->product_name = $vpsProduct->name;
        $vpsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $vpsOrderItem->gross_price = $vpsPrice->price;
        $vpsOrderItem->net_price = $vpsPrice->price;
        $vpsOrderItem->billing_period = $vpsPrice->billing_period;
        $vpsOrderItem->contract_period = $vpsPrice->contract_period;
        $vpsOrderItem->should_invoice = true;
        $vpsOrderItem->save();

        // Set OS as child of VPS in order
        $osOrderItem->parent_id = $vpsOrderItem->id;
        $osOrderItem->save();

        $vpsSubscription = new Subscription();
        $vpsSubscription->uuid = Uuid::uuid4()->toString();
        $vpsSubscription->customer_id = $customer->id;
        $vpsSubscription->product_uuid = $vpsProduct->uuid;
        $vpsSubscription->contract_period = $vpsPrice->contract_period;
        $vpsSubscription->billing_period = $vpsPrice->billing_period;
        $vpsSubscription->net_price = $vpsOrderItem->net_price;
        $vpsSubscription->gross_price = $vpsOrderItem->gross_price;
        $vpsSubscription->technical_status = TechnicalStatus::OK->value;
        $vpsSubscription->start_date = CarbonImmutable::yesterday();
        $vpsSubscription->next_billing_date = $vpsSubscription->start_date->addMonths($vpsSubscription->billing_period);
        $vpsSubscription->end_date = $vpsSubscription->start_date->addMonths($vpsSubscription->contract_period);
        $vpsSubscription->save();

        $vpsSubscriptionPrice = new SubscriptionPrice();
        $vpsSubscriptionPrice->subscription_id = $vpsSubscription->id;
        $vpsSubscriptionPrice->valid_from = $vpsSubscription->start_date;
        $vpsSubscriptionPrice->net_price = $vpsSubscription->net_price;
        $vpsSubscriptionPrice->save();

        $vpsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $vpsSubscriptionPriceComponent->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscriptionPriceComponent->type = $vpsPrice->type;
        $vpsSubscriptionPriceComponent->percentage_discount = null;
        $vpsSubscriptionPriceComponent->fixed_discount = null;
        $vpsSubscriptionPriceComponent->fixed_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->new_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->order_applied = 1;
        $vpsSubscriptionPriceComponent->save();

        $vpsSubscription->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscription->save();

        $vpsOrderItem->subscription_uuid = $vpsSubscription->uuid;
        $vpsOrderItem->save();

        $osSubscription->parent_subscription_id = $vpsSubscription->id;
        $osSubscription->save();

        $vpsDeployment = new VirtualMachineDeployment();
        $vpsDeployment->subscription_uuid = $vpsSubscription->uuid;
        $vpsDeployment->manager_domain_deployment_id = $managerDomainDeployment->id;
        $vpsDeployment->last_result = '{"message": "Command start machine success; machine now running.", "code": 0}';
        $vpsDeployment->last_result_received = CarbonImmutable::now()->subHour();
        $vpsDeployment->cloudstack_id = '477f92f6-79c4-4e31-98e1-ed3e2fb8d70f'; // Matches Mockoon
        $vpsDeployment->custom_name = 'VPS Cloud 2 Ubuntu';
        $vpsDeployment->save();

        $publicKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC+h9Y3ZvwRvRzZYIH1m4uqAF4gBH3AsPFvw1S4Gt+gWR5H50VFUFnFfrJP98kzSxg6NaWUcNe1OZUDNNuKqlzHum/JM7+lA3j5bmaatqz9h4RN83Ws9HPiZF1pVKFWRe29d0RVucAXlkQV1vuXx15IXDWYY3gydiDPaiyr5PkKpd9xG+SxTYB3IayyiBLWVH5ewUX3jFkGhLKm7ucJlbVCWJu0ZBcSoGIGlmU0aasCeoxIjq11TLoBlwvAlumJqKTZ+USeGWd+OPbyssoHjubep1EgN0REIzamIvJVSoA5gu4GGn1QNv1n4TQ+dtvh1hFFwHoPZpGj9ffaVnp3Pc4h some-random-key';
        $sshKey = new SshKey();
        $sshKey->uuid = Uuid::uuid4();
        $sshKey->customer_id = $customer->id;
        $sshKey->key_name = 'ssh-key-vps-cloud-2';
        $sshKey->public_key = $publicKey;
        $sshKey->fingerprint = '60:6E:5D:D4:56:3F:0B:3E:87:3E:DC:3A:AA:72:FC:3A:08:0B:48:37';
        $sshKey->cloudstack_ssh_name = sha1($publicKey);
        $sshKey->save();

        $managerDomainDeployment->sshKeys()->save($sshKey);
        $vpsDeployment->sshKeys()->save($sshKey);
    }

    private function vpsCloud5UbuntuSshHaarlem(Customer $customer): void
    {
        $vpsProduct = $this->referenceRepo->get(ProductReference::VPS_CLOUD_5, Product::class);
        $vpsPrice = $this->referenceRepo->get(ProductReference::VPS_CLOUD_5_REGISTRATION_PRICE, ProductPriceComponent::class);

        $managerDomainDeployment = $this->referenceRepo->get(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_HAARLEM, ManagerDomainDeployment::class);

        // OS dependencies
        $osProduct = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_SSH_REQUIRED, Product::class);
        $osPrice = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->total_price = $vpsPrice->price + $osPrice->price;
        $order->administration_fees = 0;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = false;
        $order->save();

        // OS seeding
        $osOrderItem = new OrderLineItem();
        $osOrderItem->order_id = $order->id;
        $osOrderItem->product_uuid = $osProduct->uuid;
        $osOrderItem->product_name = $osProduct->name;
        $osOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $osOrderItem->gross_price = $osPrice->price;
        $osOrderItem->net_price = $osPrice->price;
        $osOrderItem->billing_period = $osPrice->billing_period;
        $osOrderItem->contract_period = $osPrice->contract_period;
        $osOrderItem->should_invoice = true;
        $osOrderItem->save();

        $osSubscription = new Subscription();
        $osSubscription->uuid = Uuid::uuid4()->toString();
        $osSubscription->customer_id = $customer->id;
        $osSubscription->product_uuid = $osProduct->uuid;
        $osSubscription->contract_period = $osPrice->contract_period;
        $osSubscription->billing_period = $osPrice->billing_period;
        $osSubscription->net_price = $osOrderItem->net_price;
        $osSubscription->gross_price = $osOrderItem->gross_price;
        $osSubscription->technical_status = TechnicalStatus::OK->value;
        $osSubscription->start_date = CarbonImmutable::yesterday();
        $osSubscription->next_billing_date = $osSubscription->start_date->addMonths($osSubscription->billing_period);
        $osSubscription->end_date = $osSubscription->start_date->addMonths($osSubscription->contract_period);
        $osSubscription->save();

        $osSubscriptionPrice = new SubscriptionPrice();
        $osSubscriptionPrice->subscription_id = $osSubscription->id;
        $osSubscriptionPrice->valid_from = $osSubscription->start_date;
        $osSubscriptionPrice->net_price = $osSubscription->net_price;
        $osSubscriptionPrice->save();

        $osSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $osSubscriptionPriceComponent->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscriptionPriceComponent->type = $osPrice->type;
        $osSubscriptionPriceComponent->percentage_discount = null;
        $osSubscriptionPriceComponent->fixed_discount = null;
        $osSubscriptionPriceComponent->fixed_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->new_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->order_applied = 1;
        $osSubscriptionPriceComponent->save();

        $osSubscription->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscription->save();

        $osOrderItem->subscription_uuid = $osSubscription->uuid;
        $osOrderItem->save();

        // VPS seeding
        $vpsOrderItem = new OrderLineItem();
        $vpsOrderItem->order_id = $order->id;
        $vpsOrderItem->product_uuid = $vpsProduct->uuid;
        $vpsOrderItem->product_name = $vpsProduct->name;
        $vpsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $vpsOrderItem->gross_price = $vpsPrice->price;
        $vpsOrderItem->net_price = $vpsPrice->price;
        $vpsOrderItem->billing_period = $vpsPrice->billing_period;
        $vpsOrderItem->contract_period = $vpsPrice->contract_period;
        $vpsOrderItem->should_invoice = true;
        $vpsOrderItem->save();

        $osOrderItem->parent_id = $vpsOrderItem->id;
        $osOrderItem->save();

        $vpsSubscription = new Subscription();
        $vpsSubscription->uuid = Uuid::uuid4()->toString();
        $vpsSubscription->customer_id = $customer->id;
        $vpsSubscription->product_uuid = $vpsProduct->uuid;
        $vpsSubscription->contract_period = $vpsPrice->contract_period;
        $vpsSubscription->billing_period = $vpsPrice->billing_period;
        $vpsSubscription->net_price = $vpsOrderItem->net_price;
        $vpsSubscription->gross_price = $vpsOrderItem->gross_price;
        $vpsSubscription->technical_status = TechnicalStatus::OK->value;
        $vpsSubscription->start_date = CarbonImmutable::yesterday();
        $vpsSubscription->next_billing_date = $vpsSubscription->start_date->addMonths($vpsSubscription->billing_period);
        $vpsSubscription->end_date = $vpsSubscription->start_date->addMonths($vpsSubscription->contract_period);
        $vpsSubscription->save();

        $vpsSubscriptionPrice = new SubscriptionPrice();
        $vpsSubscriptionPrice->subscription_id = $vpsSubscription->id;
        $vpsSubscriptionPrice->valid_from = $vpsSubscription->start_date;
        $vpsSubscriptionPrice->net_price = $vpsSubscription->net_price;
        $vpsSubscriptionPrice->save();

        $vpsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $vpsSubscriptionPriceComponent->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscriptionPriceComponent->type = $vpsPrice->type;
        $vpsSubscriptionPriceComponent->percentage_discount = null;
        $vpsSubscriptionPriceComponent->fixed_discount = null;
        $vpsSubscriptionPriceComponent->fixed_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->new_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->order_applied = 1;
        $vpsSubscriptionPriceComponent->save();

        $vpsSubscription->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscription->save();

        $vpsOrderItem->subscription_uuid = $vpsSubscription->uuid;
        $vpsOrderItem->save();

        $osSubscription->parent_subscription_id = $vpsSubscription->id;
        $osSubscription->save();

        $vpsDeployment = new VirtualMachineDeployment();
        $vpsDeployment->subscription_uuid = $vpsSubscription->uuid;
        $vpsDeployment->manager_domain_deployment_id = $managerDomainDeployment->id;
        $vpsDeployment->last_result = '{"message": "Command stopping success; Machine now stopped", "code": 0}';
        $vpsDeployment->last_result_received = CarbonImmutable::now()->subHour();
        $vpsDeployment->cloudstack_id = 'a82b00a0-e8c4-427c-a905-0d878e8884ad'; // Matches Mockoon
        $vpsDeployment->custom_name = 'VPS Cloud 5 Ubuntu';
        $vpsDeployment->save();

        $publicKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC+h9Y3ZvwRvRzZYIH1m4uqAF4gBH3AsPFvw1S4Gt+gWR5H50VFUFnFfrJP98kzSxg6NaWUcNe1OZUDNNuKqlzHum/JM7+lA3j5bmaatqz9h4RN83Ws9HPiZF1pVKFWRe29d0RVucAXlkQV1vuXx15IXDWYY3gydiDPaiyr5PkKpd9xG+SxTYB3IayyiBLWVH5ewU';
        $sshKey = new SshKey();
        $sshKey->uuid = Uuid::uuid4();
        $sshKey->customer_id = $customer->id;
        $sshKey->key_name = 'ssh-key-vps-cloud-5';
        $sshKey->public_key = $publicKey;
        $sshKey->fingerprint = '54:f7:dc:ac:19:2b:96:0e:5c:c4:db:74:09:09:73:cb';
        $sshKey->cloudstack_ssh_name = sha1($publicKey);
        $sshKey->save();

        $managerDomainDeployment->sshKeys()->save($sshKey);
        $vpsDeployment->sshKeys()->save($sshKey);
    }

    private function vpsCloud10UbuntuHaarlem(Customer $customer): void
    {
        $vpsProduct = $this->referenceRepo->get(ProductReference::VPS_CLOUD_10, Product::class);
        $vpsPrice = $this->referenceRepo->get(ProductReference::VPS_CLOUD_10_REGISTRATION_PRICE, ProductPriceComponent::class);

        $managerDomainDeployment = $this->referenceRepo->get(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_HAARLEM, ManagerDomainDeployment::class);

        // OS dependencies
        $osProduct = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU, Product::class);
        $osPrice = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->total_price = $vpsPrice->price + $osPrice->price;
        $order->administration_fees = 0;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = false;
        $order->save();

        // OS seeding
        $osOrderItem = new OrderLineItem();
        $osOrderItem->order_id = $order->id;
        $osOrderItem->product_uuid = $osProduct->uuid;
        $osOrderItem->product_name = $osProduct->name;
        $osOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $osOrderItem->gross_price = $osPrice->price;
        $osOrderItem->net_price = $osPrice->price;
        $osOrderItem->billing_period = $osPrice->billing_period;
        $osOrderItem->contract_period = $osPrice->contract_period;
        $osOrderItem->should_invoice = true;
        $osOrderItem->save();

        $osSubscription = new Subscription();
        $osSubscription->uuid = Uuid::uuid4()->toString();
        $osSubscription->customer_id = $customer->id;
        $osSubscription->product_uuid = $osProduct->uuid;
        $osSubscription->contract_period = $osPrice->contract_period;
        $osSubscription->billing_period = $osPrice->billing_period;
        $osSubscription->net_price = $osOrderItem->net_price;
        $osSubscription->gross_price = $osOrderItem->gross_price;
        $osSubscription->technical_status = TechnicalStatus::OK->value;
        $osSubscription->start_date = CarbonImmutable::yesterday();
        $osSubscription->next_billing_date = $osSubscription->start_date->addMonths($osSubscription->billing_period);
        $osSubscription->end_date = $osSubscription->start_date->addMonths($osSubscription->contract_period);
        $osSubscription->save();

        $osSubscriptionPrice = new SubscriptionPrice();
        $osSubscriptionPrice->subscription_id = $osSubscription->id;
        $osSubscriptionPrice->valid_from = $osSubscription->start_date;
        $osSubscriptionPrice->net_price = $osSubscription->net_price;
        $osSubscriptionPrice->save();

        $osSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $osSubscriptionPriceComponent->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscriptionPriceComponent->type = $osPrice->type;
        $osSubscriptionPriceComponent->percentage_discount = null;
        $osSubscriptionPriceComponent->fixed_discount = null;
        $osSubscriptionPriceComponent->fixed_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->new_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->order_applied = 1;
        $osSubscriptionPriceComponent->save();

        $osSubscription->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscription->save();

        $osOrderItem->subscription_uuid = $osSubscription->uuid;
        $osOrderItem->save();

        // VPS seeding
        $vpsOrderItem = new OrderLineItem();
        $vpsOrderItem->order_id = $order->id;
        $vpsOrderItem->product_uuid = $vpsProduct->uuid;
        $vpsOrderItem->product_name = $vpsProduct->name;
        $vpsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $vpsOrderItem->gross_price = $vpsPrice->price;
        $vpsOrderItem->net_price = $vpsPrice->price;
        $vpsOrderItem->billing_period = $vpsPrice->billing_period;
        $vpsOrderItem->contract_period = $vpsPrice->contract_period;
        $vpsOrderItem->should_invoice = true;
        $vpsOrderItem->save();

        $osOrderItem->parent_id = $vpsOrderItem->id;
        $osOrderItem->save();

        $vpsSubscription = new Subscription();
        $vpsSubscription->uuid = Uuid::uuid4()->toString();
        $vpsSubscription->customer_id = $customer->id;
        $vpsSubscription->product_uuid = $vpsProduct->uuid;
        $vpsSubscription->contract_period = $vpsPrice->contract_period;
        $vpsSubscription->billing_period = $vpsPrice->billing_period;
        $vpsSubscription->net_price = $vpsOrderItem->net_price;
        $vpsSubscription->gross_price = $vpsOrderItem->gross_price;
        $vpsSubscription->technical_status = TechnicalStatus::OK->value;
        $vpsSubscription->start_date = CarbonImmutable::yesterday();
        $vpsSubscription->next_billing_date = $vpsSubscription->start_date->addMonths($vpsSubscription->billing_period);
        $vpsSubscription->end_date = $vpsSubscription->start_date->addMonths($vpsSubscription->contract_period);
        $vpsSubscription->save();

        $vpsSubscriptionPrice = new SubscriptionPrice();
        $vpsSubscriptionPrice->subscription_id = $vpsSubscription->id;
        $vpsSubscriptionPrice->valid_from = $vpsSubscription->start_date;
        $vpsSubscriptionPrice->net_price = $vpsSubscription->net_price;
        $vpsSubscriptionPrice->save();

        $vpsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $vpsSubscriptionPriceComponent->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscriptionPriceComponent->type = $vpsPrice->type;
        $vpsSubscriptionPriceComponent->percentage_discount = null;
        $vpsSubscriptionPriceComponent->fixed_discount = null;
        $vpsSubscriptionPriceComponent->fixed_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->new_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->order_applied = 1;
        $vpsSubscriptionPriceComponent->save();

        $vpsSubscription->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscription->save();

        $vpsOrderItem->subscription_uuid = $vpsSubscription->uuid;
        $vpsOrderItem->save();

        $osSubscription->parent_subscription_id = $vpsSubscription->id;
        $osSubscription->save();

        $vpsDeployment = new VirtualMachineDeployment();
        $vpsDeployment->subscription_uuid = $vpsSubscription->uuid;
        $vpsDeployment->manager_domain_deployment_id = $managerDomainDeployment->id;
        $vpsDeployment->last_result = '{"message": "deploying VM success", "code": 0}';
        $vpsDeployment->last_result_received = CarbonImmutable::now()->subHour();
        $vpsDeployment->cloudstack_id = '5b8465bf-75b5-4dc5-867e-be9d8d2af170'; // Matches Mockoon
        $vpsDeployment->save();
    }

    private function vpsCloud20UbuntuSshAmsterdam(Customer $customer): void
    {
        $vpsProduct = $this->referenceRepo->get(ProductReference::VPS_CLOUD_20, Product::class);
        $vpsPrice = $this->referenceRepo->get(ProductReference::VPS_CLOUD_20_REGISTRATION_PRICE, ProductPriceComponent::class);

        $managerDomainDeployment = $this->referenceRepo->get(ScenarioReference::TEST_KEES_VPS_MANAGER_DOMAIN_DEPLOYMENT_AMSTERDAM, ManagerDomainDeployment::class);

        // OS dependencies
        $osProduct = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_SSH_REQUIRED, Product::class);
        $osPrice = $this->referenceRepo->get(ProductReference::VPS_OS_UBUNTU_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->total_price = $vpsPrice->price + $osPrice->price;
        $order->administration_fees = 0;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        // OS seeding
        $osOrderItem = new OrderLineItem();
        $osOrderItem->order_id = $order->id;
        $osOrderItem->product_uuid = $osProduct->uuid;
        $osOrderItem->product_name = $osProduct->name;
        $osOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $osOrderItem->gross_price = $osPrice->price;
        $osOrderItem->net_price = $osPrice->price;
        $osOrderItem->billing_period = $osPrice->billing_period;
        $osOrderItem->contract_period = $osPrice->contract_period;
        $osOrderItem->should_invoice = true;
        $osOrderItem->save();

        $osSubscription = new Subscription();
        $osSubscription->uuid = Uuid::uuid4()->toString();
        $osSubscription->customer_id = $customer->id;
        $osSubscription->product_uuid = $osProduct->uuid;
        $osSubscription->contract_period = $osPrice->contract_period;
        $osSubscription->billing_period = $osPrice->billing_period;
        $osSubscription->net_price = $osOrderItem->net_price;
        $osSubscription->gross_price = $osOrderItem->gross_price;
        $osSubscription->technical_status = TechnicalStatus::OK->value;
        $osSubscription->start_date = CarbonImmutable::yesterday();
        $osSubscription->next_billing_date = $osSubscription->start_date->addMonths($osSubscription->billing_period);
        $osSubscription->end_date = $osSubscription->start_date->addMonths($osSubscription->contract_period);
        $osSubscription->save();

        $osSubscriptionPrice = new SubscriptionPrice();
        $osSubscriptionPrice->subscription_id = $osSubscription->id;
        $osSubscriptionPrice->valid_from = $osSubscription->start_date;
        $osSubscriptionPrice->net_price = $osSubscription->net_price;
        $osSubscriptionPrice->save();

        $osSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $osSubscriptionPriceComponent->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscriptionPriceComponent->type = $osPrice->type;
        $osSubscriptionPriceComponent->percentage_discount = null;
        $osSubscriptionPriceComponent->fixed_discount = null;
        $osSubscriptionPriceComponent->fixed_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->new_price = $osSubscription->net_price;
        $osSubscriptionPriceComponent->order_applied = 1;
        $osSubscriptionPriceComponent->save();

        $osSubscription->subscription_price_id = $osSubscriptionPrice->id;
        $osSubscription->save();

        $osOrderItem->subscription_uuid = $osSubscription->uuid;
        $osOrderItem->save();

        // VPS seeding
        $vpsOrderItem = new OrderLineItem();
        $vpsOrderItem->order_id = $order->id;
        $vpsOrderItem->product_uuid = $vpsProduct->uuid;
        $vpsOrderItem->product_name = $vpsProduct->name;
        $vpsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $vpsOrderItem->gross_price = $vpsPrice->price;
        $vpsOrderItem->net_price = $vpsPrice->price;
        $vpsOrderItem->billing_period = $vpsPrice->billing_period;
        $vpsOrderItem->contract_period = $vpsPrice->contract_period;
        $vpsOrderItem->should_invoice = true;
        $vpsOrderItem->save();

        $vpsSubscription = new Subscription();
        $vpsSubscription->uuid = Uuid::uuid4()->toString();
        $vpsSubscription->customer_id = $customer->id;
        $vpsSubscription->product_uuid = $vpsProduct->uuid;
        $vpsSubscription->contract_period = $vpsPrice->contract_period;
        $vpsSubscription->billing_period = $vpsPrice->billing_period;
        $vpsSubscription->net_price = $vpsOrderItem->net_price;
        $vpsSubscription->gross_price = $vpsOrderItem->gross_price;
        $vpsSubscription->technical_status = TechnicalStatus::OK->value;
        $vpsSubscription->start_date = CarbonImmutable::yesterday();
        $vpsSubscription->next_billing_date = $vpsSubscription->start_date->addMonths($vpsSubscription->billing_period);
        $vpsSubscription->end_date = $vpsSubscription->start_date->addMonths($vpsSubscription->contract_period);
        $vpsSubscription->save();

        $vpsSubscriptionPrice = new SubscriptionPrice();
        $vpsSubscriptionPrice->subscription_id = $vpsSubscription->id;
        $vpsSubscriptionPrice->valid_from = $vpsSubscription->start_date;
        $vpsSubscriptionPrice->net_price = $vpsSubscription->net_price;
        $vpsSubscriptionPrice->save();

        $vpsSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $vpsSubscriptionPriceComponent->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscriptionPriceComponent->type = $vpsPrice->type;
        $vpsSubscriptionPriceComponent->percentage_discount = null;
        $vpsSubscriptionPriceComponent->fixed_discount = null;
        $vpsSubscriptionPriceComponent->fixed_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->new_price = $vpsSubscription->net_price;
        $vpsSubscriptionPriceComponent->order_applied = 1;
        $vpsSubscriptionPriceComponent->save();

        $vpsSubscription->subscription_price_id = $vpsSubscriptionPrice->id;
        $vpsSubscription->save();

        $osOrderItem->parent_id = $vpsOrderItem->id;
        $osOrderItem->save();

        $vpsOrderItem->subscription_uuid = $vpsSubscription->uuid;
        $vpsOrderItem->save();

        $osSubscription->parent_subscription_id = $vpsSubscription->id;
        $osSubscription->save();

        $vpsDeployment = new VirtualMachineDeployment();
        $vpsDeployment->subscription_uuid = $vpsSubscription->uuid;
        $vpsDeployment->manager_domain_deployment_id = $managerDomainDeployment->id;
        $vpsDeployment->last_result = '{"message": "deploying VM success", "code": 0}';
        $vpsDeployment->last_result_received = CarbonImmutable::now()->subHour();
        $vpsDeployment->cloudstack_id = 'f4a095de-7897-4208-af5b-90163c3120f2'; // Matches Mockoon
        $vpsDeployment->custom_name = 'VPS Cloud 20 Ubuntu';
        $vpsDeployment->save();
    }

    private function sitebuilder(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::SITEBUILDER_PROVIDER, Provider::class);
        $mailOnlyProvider = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::SITEBUILDER_SERVER, Server::class);
        $mailServer = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class);

        // Domain dependencies
        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependencies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sitebuilderOrderItem = new OrderLineItem();
        $sitebuilderOrderItem->order_id = $order->id;
        $sitebuilderOrderItem->domain = 'sitebuilder.nl';
        $sitebuilderOrderItem->product_uuid = $product->uuid;
        $sitebuilderOrderItem->product_name = $product->name;
        $sitebuilderOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $sitebuilderOrderItem->gross_price = $price->price;
        $sitebuilderOrderItem->net_price = $price->price;
        $sitebuilderOrderItem->billing_period = $price->billing_period;
        $sitebuilderOrderItem->contract_period = $price->contract_period;
        $sitebuilderOrderItem->processed_at = CarbonImmutable::now();
        $sitebuilderOrderItem->should_invoice = true;
        $sitebuilderOrderItem->save();

        $sitebuilderSubscription = new Subscription();
        $sitebuilderSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderSubscription->customer_id = $customer->id;
        $sitebuilderSubscription->product_uuid = $product->uuid;
        $sitebuilderSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderSubscription->contract_period = $price->contract_period;
        $sitebuilderSubscription->billing_period = $price->billing_period;
        $sitebuilderSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderSubscription->next_billing_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->billing_period);
        $sitebuilderSubscription->end_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->contract_period);
        $sitebuilderSubscription->save();

        $sitebuilderSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderSubscriptionPrice->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderSubscriptionPrice->valid_from = $sitebuilderSubscription->start_date;
        $sitebuilderSubscriptionPrice->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPrice->save();

        $sitebuilderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderSubscriptionPriceComponent->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscriptionPriceComponent->type = $price->type;
        $sitebuilderSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->new_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderSubscriptionPriceComponent->save();

        $sitebuilderSubscription->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderSubscription->uuid;
        $sitebuilderOrderItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $sitebuilderSubscription->uuid;
        $hostingDeployment->provider_id = null;
        $hostingDeployment->server_id = null;
        $hostingDeployment->sitebuilder_provider_id = $provider->id;
        $hostingDeployment->mail_only_provider_id = $mailOnlyProvider->id;
        $hostingDeployment->mailOnlyServer()->associate($mailServer);
        $hostingDeployment->basekit_user_ref = 123;
        $hostingDeployment->basekit_site_ref = 456;
        $hostingDeployment->basekit_server_id = $server->id;
        $hostingDeployment->directadmin_customer_username = 'da-sitebuilder-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $sitebuilderSubscription->start_date->addHour();
        $hostingDeployment->save();

        $sitebuilderInvoiceItem = new Invoice();
        $sitebuilderInvoiceItem->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderInvoiceItem->customer_id = $customer->id;
        $sitebuilderInvoiceItem->product_id = $product->id;
        $sitebuilderInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $sitebuilderInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $sitebuilderInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $sitebuilderInvoiceItem->paid = false;
        $sitebuilderInvoiceItem->start_date = $sitebuilderSubscription->start_date;
        $sitebuilderInvoiceItem->end_date = $sitebuilderSubscription->next_billing_date;
        $sitebuilderInvoiceItem->period = $sitebuilderSubscription->contract_period;
        $sitebuilderInvoiceItem->gross_price = $sitebuilderSubscription->gross_price;
        $sitebuilderInvoiceItem->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderInvoiceItem->title = $sitebuilderSubscription->domain ?? $sitebuilderSubscription->product->name;
        $sitebuilderInvoiceItem->description = sprintf('%s for %s', $sitebuilderSubscription->product->name, $sitebuilderSubscription->domain);
        $sitebuilderInvoiceItem->group_label = null;
        $sitebuilderInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $sitebuilderInvoiceItem->prepaid_reference = null;
        $sitebuilderInvoiceItem->save();

        // Domain seeding
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $sitebuilderOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $domainProduct->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $sitebuilderOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function provisionSitebuilderBasekit(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sitebuilderOrderItem = new OrderLineItem();
        $sitebuilderOrderItem->order_id = $order->id;
        $sitebuilderOrderItem->domain = 'provisioning-sitebuilder-basekit.nl';
        $sitebuilderOrderItem->product_uuid = $product->uuid;
        $sitebuilderOrderItem->product_name = $product->name;
        $sitebuilderOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $sitebuilderOrderItem->gross_price = $price->price;
        $sitebuilderOrderItem->net_price = $price->price;
        $sitebuilderOrderItem->billing_period = $price->billing_period;
        $sitebuilderOrderItem->contract_period = $price->contract_period;
        $sitebuilderOrderItem->processed_at = CarbonImmutable::now();
        $sitebuilderOrderItem->should_invoice = true;
        $sitebuilderOrderItem->save();

        $sitebuilderSubscription = new Subscription();
        $sitebuilderSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderSubscription->customer_id = $customer->id;
        $sitebuilderSubscription->product_uuid = $product->uuid;
        $sitebuilderSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderSubscription->contract_period = $price->contract_period;
        $sitebuilderSubscription->billing_period = $price->billing_period;
        $sitebuilderSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderSubscription->next_billing_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->billing_period);
        $sitebuilderSubscription->end_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->contract_period);
        $sitebuilderSubscription->save();

        $sitebuilderSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderSubscriptionPrice->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderSubscriptionPrice->valid_from = $sitebuilderSubscription->start_date;
        $sitebuilderSubscriptionPrice->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPrice->save();

        $sitebuilderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderSubscriptionPriceComponent->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscriptionPriceComponent->type = $price->type;
        $sitebuilderSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->new_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderSubscriptionPriceComponent->save();

        $sitebuilderSubscription->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderSubscription->uuid;
        $sitebuilderOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 'totally invalid'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $provisionRequest->request_type = ProvisionType::SITEBUILDER;
        $provisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'basekit_package' => ['invalid_package_ref' => 'Invalid package reference given.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 1234], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $retryProvisionRequest->request_type = ProvisionType::SITEBUILDER;
        $retryProvisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created sitebuilder successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = Uuid::uuid4();
        $sitebuilderDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $sitebuilderDeployment->domain = $sitebuilderSubscription->domain;
        $sitebuilderDeployment->save();

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $basekitContext->user_ref = 1337;
        $basekitContext->save();

        $basekitDeployment = new BasekitSitebuilderDeployment();
        $basekitDeployment->uuid = Uuid::uuid4();
        $basekitDeployment->site_ref = 1337;
        $basekitDeployment->sitebuilder_deployment_id = $sitebuilderDeployment->id;
        $basekitDeployment->save();

        // We still need an 'old' hosting deployment for the mail-only dependency.
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $sitebuilderSubscription->uuid;
        $hostingDeployment->provider_id = null;
        $hostingDeployment->server_id = null;
        $hostingDeployment->mail_only_provider_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class)->id;
        $hostingDeployment->mailOnlyServer()->associate($this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class));
        $hostingDeployment->directadmin_customer_username = 'da-sitebuilder-mail-only-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $sitebuilderSubscription->start_date->addHour();

        // Deliberately do not set the old sitebuilder hosting deployment settings, these should not be used for this product.
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $sitebuilderSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $sitebuilderSubscription->start_date;
        $invoiceItem->end_date = $sitebuilderSubscription->next_billing_date;
        $invoiceItem->period = $sitebuilderSubscription->contract_period;
        $invoiceItem->net_price = $sitebuilderSubscription->net_price;
        $invoiceItem->gross_price = $sitebuilderSubscription->gross_price;
        $invoiceItem->title = $sitebuilderSubscription->domain ?? $sitebuilderSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $sitebuilderSubscription->product->name, $sitebuilderSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $sitebuilderOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $sitebuilderOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function provisionSitebuilderBasekitWithAddons(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SITEBUILDER_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $productAddon = $this->referenceRepo->get(ProductReference::BASEKIT_ADD_ON_BOOKING, Product::class);
        $addonPrice = $this->referenceRepo->get(ProductReference::BASEKIT_ADD_ON_BOOKING_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sitebuilderOrderItem = new OrderLineItem();
        $sitebuilderOrderItem->order_id = $order->id;
        $sitebuilderOrderItem->domain = 'provisioning-sitebuilder-basekit-booking-addon.nl';
        $sitebuilderOrderItem->product_uuid = $product->uuid;
        $sitebuilderOrderItem->product_name = $product->name;
        $sitebuilderOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $sitebuilderOrderItem->gross_price = $price->price;
        $sitebuilderOrderItem->net_price = $price->price;
        $sitebuilderOrderItem->billing_period = $price->billing_period;
        $sitebuilderOrderItem->contract_period = $price->contract_period;
        $sitebuilderOrderItem->processed_at = CarbonImmutable::now();
        $sitebuilderOrderItem->should_invoice = true;
        $sitebuilderOrderItem->save();

        $sitebuilderSubscription = new Subscription();
        $sitebuilderSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderSubscription->customer_id = $customer->id;
        $sitebuilderSubscription->product_uuid = $product->uuid;
        $sitebuilderSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderSubscription->contract_period = $price->contract_period;
        $sitebuilderSubscription->billing_period = $price->billing_period;
        $sitebuilderSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderSubscription->next_billing_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->billing_period);
        $sitebuilderSubscription->end_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->contract_period);
        $sitebuilderSubscription->save();

        $sitebuilderSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderSubscriptionPrice->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderSubscriptionPrice->valid_from = $sitebuilderSubscription->start_date;
        $sitebuilderSubscriptionPrice->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPrice->save();

        $sitebuilderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderSubscriptionPriceComponent->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscriptionPriceComponent->type = $price->type;
        $sitebuilderSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->new_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderSubscriptionPriceComponent->save();

        $sitebuilderSubscription->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderSubscription->uuid;
        $sitebuilderOrderItem->save();

        $sitebuilderBookingAddonSubscription = new Subscription();
        $sitebuilderBookingAddonSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderBookingAddonSubscription->parent_subscription_id = $sitebuilderSubscription->id;
        $sitebuilderBookingAddonSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderBookingAddonSubscription->customer_id = $customer->id;
        $sitebuilderBookingAddonSubscription->product_uuid = $productAddon->uuid;
        $sitebuilderBookingAddonSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderBookingAddonSubscription->contract_period = $addonPrice->contract_period;
        $sitebuilderBookingAddonSubscription->billing_period = $addonPrice->billing_period;
        $sitebuilderBookingAddonSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderBookingAddonSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderBookingAddonSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderBookingAddonSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderBookingAddonSubscription->next_billing_date = $sitebuilderBookingAddonSubscription->start_date->addMonths($sitebuilderBookingAddonSubscription->billing_period);
        $sitebuilderBookingAddonSubscription->end_date = $sitebuilderBookingAddonSubscription->start_date->addMonths($sitebuilderBookingAddonSubscription->contract_period);
        $sitebuilderBookingAddonSubscription->save();

        $sitebuilderBookingAddonSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderBookingAddonSubscriptionPrice->subscription_id = $sitebuilderBookingAddonSubscription->id;
        $sitebuilderBookingAddonSubscriptionPrice->valid_from = $sitebuilderBookingAddonSubscription->start_date;
        $sitebuilderBookingAddonSubscriptionPrice->net_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPrice->save();

        $sitebuilderBookingAddonSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderBookingAddonSubscriptionPriceComponent->subscription_price_id = $sitebuilderBookingAddonSubscriptionPrice->id;
        $sitebuilderBookingAddonSubscriptionPriceComponent->type = $addonPrice->type;
        $sitebuilderBookingAddonSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderBookingAddonSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderBookingAddonSubscriptionPriceComponent->fixed_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPriceComponent->new_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderBookingAddonSubscriptionPriceComponent->save();

        $sitebuilderBookingAddonSubscription->subscription_price_id = $sitebuilderBookingAddonSubscriptionPrice->id;
        $sitebuilderBookingAddonSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderBookingAddonSubscription->uuid;
        $sitebuilderOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 'invalid'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $provisionRequest->request_type = ProvisionType::SITEBUILDER;
        $provisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'basekit_package' => ['invalid_package_ref' => 'Invalid package reference given.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 1234], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $retryProvisionRequest->request_type = ProvisionType::SITEBUILDER;
        $retryProvisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created sitebuilder successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = Uuid::uuid4();
        $sitebuilderDeployment->domain = $sitebuilderSubscription->domain;
        $sitebuilderDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $sitebuilderDeployment->save();

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $basekitContext->user_ref = 1338;
        $basekitContext->save();

        $basekitDeployment = new BasekitSitebuilderDeployment();
        $basekitDeployment->uuid = Uuid::uuid4();
        $basekitDeployment->site_ref = 1337;
        $basekitDeployment->sitebuilder_deployment_id = $sitebuilderDeployment->id;
        $basekitDeployment->save();

        // We still need an 'old' hosting deployment for the mail-only dependency.
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $sitebuilderSubscription->uuid;
        $hostingDeployment->provider_id = null;
        $hostingDeployment->server_id = null;
        $hostingDeployment->mail_only_provider_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class)->id;
        $hostingDeployment->mailOnlyServer()->associate($this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class));
        $hostingDeployment->directadmin_customer_username = 'da-sitebuilder-mail-only-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $sitebuilderSubscription->start_date->addHour();

        // Deliberately do not set the old sitebuilder hosting deployment settings, these should not be used for this product.
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $sitebuilderSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $sitebuilderSubscription->start_date;
        $invoiceItem->end_date = $sitebuilderSubscription->next_billing_date;
        $invoiceItem->period = $sitebuilderSubscription->contract_period;
        $invoiceItem->net_price = $sitebuilderSubscription->net_price;
        $invoiceItem->gross_price = $sitebuilderSubscription->gross_price;
        $invoiceItem->title = $sitebuilderSubscription->domain ?? $sitebuilderSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $sitebuilderSubscription->product->name, $sitebuilderSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $sitebuilderOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $sitebuilderOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function provisionSitebuilderWebShopBasekitWithAddons(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $productAddon = $this->referenceRepo->get(ProductReference::BASEKIT_ADD_ON_BOOKING, Product::class);
        $addonPrice = $this->referenceRepo->get(ProductReference::BASEKIT_ADD_ON_BOOKING_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sitebuilderOrderItem = new OrderLineItem();
        $sitebuilderOrderItem->order_id = $order->id;
        $sitebuilderOrderItem->domain = 'provisioning-sitebuilder-shop-booking-addon.nl';
        $sitebuilderOrderItem->product_uuid = $product->uuid;
        $sitebuilderOrderItem->product_name = $product->name;
        $sitebuilderOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $sitebuilderOrderItem->gross_price = $price->price;
        $sitebuilderOrderItem->net_price = $price->price;
        $sitebuilderOrderItem->billing_period = $price->billing_period;
        $sitebuilderOrderItem->contract_period = $price->contract_period;
        $sitebuilderOrderItem->processed_at = CarbonImmutable::now();
        $sitebuilderOrderItem->should_invoice = true;
        $sitebuilderOrderItem->save();

        $sitebuilderSubscription = new Subscription();
        $sitebuilderSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderSubscription->customer_id = $customer->id;
        $sitebuilderSubscription->product_uuid = $product->uuid;
        $sitebuilderSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderSubscription->contract_period = $price->contract_period;
        $sitebuilderSubscription->billing_period = $price->billing_period;
        $sitebuilderSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderSubscription->next_billing_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->billing_period);
        $sitebuilderSubscription->end_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->contract_period);
        $sitebuilderSubscription->save();

        $sitebuilderSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderSubscriptionPrice->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderSubscriptionPrice->valid_from = $sitebuilderSubscription->start_date;
        $sitebuilderSubscriptionPrice->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPrice->save();

        $sitebuilderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderSubscriptionPriceComponent->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscriptionPriceComponent->type = $price->type;
        $sitebuilderSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->new_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderSubscriptionPriceComponent->save();

        $sitebuilderSubscription->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderSubscription->uuid;
        $sitebuilderOrderItem->save();

        $sitebuilderBookingAddonSubscription = new Subscription();
        $sitebuilderBookingAddonSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderBookingAddonSubscription->parent_subscription_id = $sitebuilderSubscription->id;
        $sitebuilderBookingAddonSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderBookingAddonSubscription->customer_id = $customer->id;
        $sitebuilderBookingAddonSubscription->product_uuid = $productAddon->uuid;
        $sitebuilderBookingAddonSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderBookingAddonSubscription->contract_period = $addonPrice->contract_period;
        $sitebuilderBookingAddonSubscription->billing_period = $addonPrice->billing_period;
        $sitebuilderBookingAddonSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderBookingAddonSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderBookingAddonSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderBookingAddonSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderBookingAddonSubscription->next_billing_date = $sitebuilderBookingAddonSubscription->start_date->addMonths($sitebuilderBookingAddonSubscription->billing_period);
        $sitebuilderBookingAddonSubscription->end_date = $sitebuilderBookingAddonSubscription->start_date->addMonths($sitebuilderBookingAddonSubscription->contract_period);
        $sitebuilderBookingAddonSubscription->save();

        $sitebuilderBookingAddonSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderBookingAddonSubscriptionPrice->subscription_id = $sitebuilderBookingAddonSubscription->id;
        $sitebuilderBookingAddonSubscriptionPrice->valid_from = $sitebuilderBookingAddonSubscription->start_date;
        $sitebuilderBookingAddonSubscriptionPrice->net_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPrice->save();

        $sitebuilderBookingAddonSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderBookingAddonSubscriptionPriceComponent->subscription_price_id = $sitebuilderBookingAddonSubscriptionPrice->id;
        $sitebuilderBookingAddonSubscriptionPriceComponent->type = $addonPrice->type;
        $sitebuilderBookingAddonSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderBookingAddonSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderBookingAddonSubscriptionPriceComponent->fixed_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPriceComponent->new_price = $sitebuilderBookingAddonSubscription->net_price;
        $sitebuilderBookingAddonSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderBookingAddonSubscriptionPriceComponent->save();

        $sitebuilderBookingAddonSubscription->subscription_price_id = $sitebuilderBookingAddonSubscriptionPrice->id;
        $sitebuilderBookingAddonSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderBookingAddonSubscription->uuid;
        $sitebuilderOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 'invalid_ref'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $provisionRequest->request_type = ProvisionType::SITEBUILDER;
        $provisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'basekit_package' => ['invalid_package_ref' => 'Invalid package reference given.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 123], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $retryProvisionRequest->request_type = ProvisionType::SITEBUILDER;
        $retryProvisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created sitebuilder successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = Uuid::uuid4();
        $sitebuilderDeployment->domain = $sitebuilderSubscription->domain;
        $sitebuilderDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $sitebuilderDeployment->save();

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $basekitContext->user_ref = 1339;
        $basekitContext->save();

        $basekitDeployment = new BasekitSitebuilderDeployment();
        $basekitDeployment->uuid = Uuid::uuid4();
        $basekitDeployment->site_ref = 1337;
        $basekitDeployment->sitebuilder_deployment_id = $sitebuilderDeployment->id;
        $basekitDeployment->save();

        // We still need an 'old' hosting deployment for the mail-only dependency.
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $sitebuilderSubscription->uuid;
        $hostingDeployment->provider_id = null;
        $hostingDeployment->server_id = null;
        $hostingDeployment->mail_only_provider_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class)->id;
        $hostingDeployment->mailOnlyServer()->associate($this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class));
        $hostingDeployment->directadmin_customer_username = 'da-sitebuilder-mail-only-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $sitebuilderSubscription->start_date->addHour();

        // Deliberately do not set the old sitebuilder hosting deployment settings, these should not be used for this product.
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $sitebuilderSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $sitebuilderSubscription->start_date;
        $invoiceItem->end_date = $sitebuilderSubscription->next_billing_date;
        $invoiceItem->period = $sitebuilderSubscription->contract_period;
        $invoiceItem->net_price = $sitebuilderSubscription->net_price;
        $invoiceItem->gross_price = $sitebuilderSubscription->gross_price;
        $invoiceItem->title = $sitebuilderSubscription->domain ?? $sitebuilderSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $sitebuilderSubscription->product->name, $sitebuilderSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $sitebuilderOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $sitebuilderOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function provisionSitebuilderWebshop(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SITEBUILDER_SHOP_BASEKIT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sitebuilderOrderItem = new OrderLineItem();
        $sitebuilderOrderItem->order_id = $order->id;
        $sitebuilderOrderItem->domain = 'provisioning-sitebuilder-shop.nl';
        $sitebuilderOrderItem->product_uuid = $product->uuid;
        $sitebuilderOrderItem->product_name = $product->name;
        $sitebuilderOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $sitebuilderOrderItem->gross_price = $price->price;
        $sitebuilderOrderItem->net_price = $price->price;
        $sitebuilderOrderItem->billing_period = $price->billing_period;
        $sitebuilderOrderItem->contract_period = $price->contract_period;
        $sitebuilderOrderItem->processed_at = CarbonImmutable::now();
        $sitebuilderOrderItem->should_invoice = true;
        $sitebuilderOrderItem->save();

        $sitebuilderSubscription = new Subscription();
        $sitebuilderSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $sitebuilderSubscription->uuid = Uuid::uuid4()->toString();
        $sitebuilderSubscription->customer_id = $customer->id;
        $sitebuilderSubscription->product_uuid = $product->uuid;
        $sitebuilderSubscription->domain = $sitebuilderOrderItem->domain;
        $sitebuilderSubscription->contract_period = $price->contract_period;
        $sitebuilderSubscription->billing_period = $price->billing_period;
        $sitebuilderSubscription->net_price = $sitebuilderOrderItem->net_price;
        $sitebuilderSubscription->gross_price = $sitebuilderOrderItem->gross_price;
        $sitebuilderSubscription->technical_status = TechnicalStatus::OK->value;
        $sitebuilderSubscription->start_date = CarbonImmutable::yesterday();
        $sitebuilderSubscription->next_billing_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->billing_period);
        $sitebuilderSubscription->end_date = $sitebuilderSubscription->start_date->addMonths($sitebuilderSubscription->contract_period);
        $sitebuilderSubscription->save();

        $sitebuilderSubscriptionPrice = new SubscriptionPrice();
        $sitebuilderSubscriptionPrice->subscription_id = $sitebuilderSubscription->id;
        $sitebuilderSubscriptionPrice->valid_from = $sitebuilderSubscription->start_date;
        $sitebuilderSubscriptionPrice->net_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPrice->save();

        $sitebuilderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $sitebuilderSubscriptionPriceComponent->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscriptionPriceComponent->type = $price->type;
        $sitebuilderSubscriptionPriceComponent->percentage_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_discount = null;
        $sitebuilderSubscriptionPriceComponent->fixed_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->new_price = $sitebuilderSubscription->net_price;
        $sitebuilderSubscriptionPriceComponent->order_applied = 1;
        $sitebuilderSubscriptionPriceComponent->save();

        $sitebuilderSubscription->subscription_price_id = $sitebuilderSubscriptionPrice->id;
        $sitebuilderSubscription->save();

        $sitebuilderOrderItem->subscription_uuid = $sitebuilderSubscription->uuid;
        $sitebuilderOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 3], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $provisionRequest->request_type = ProvisionType::SITEBUILDER;
        $provisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'basekit_package' => ['invalid_package_ref' => 'Invalid package reference given.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($sitebuilderSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $sitebuilderOrderItem->domain, 'package_ref' => 3], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_SITEBUILDER;
        $retryProvisionRequest->request_type = ProvisionType::SITEBUILDER;
        $retryProvisionRequest->provision_provider = ProvisionProvider::BASEKIT;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created sitebuilder successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $sitebuilderDeployment = new SitebuilderDeployment();
        $sitebuilderDeployment->uuid = Uuid::uuid4();
        $sitebuilderDeployment->domain = $sitebuilderSubscription->domain;
        $sitebuilderDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $sitebuilderDeployment->save();

        $basekitContext = new BasekitContext();
        $basekitContext->context_uuid = Uuid::fromString($sitebuilderSubscription->uuid);
        $basekitContext->user_ref = 1340;
        $basekitContext->save();

        $basekitDeployment = new BasekitSitebuilderDeployment();
        $basekitDeployment->uuid = Uuid::uuid4();
        $basekitDeployment->site_ref = 1337;
        $basekitDeployment->sitebuilder_deployment_id = $sitebuilderDeployment->id;
        $basekitDeployment->save();

        // We still need an 'old' hosting deployment for the mail-only dependency.
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $sitebuilderSubscription->uuid;
        $hostingDeployment->provider_id = null;
        $hostingDeployment->server_id = null;
        $hostingDeployment->mail_only_provider_id = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class)->id;
        $hostingDeployment->mailOnlyServer()->associate($this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class));
        $hostingDeployment->directadmin_customer_username = 'da-sitebuilder-mail-only-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $sitebuilderSubscription->start_date->addHour();

        // Deliberately do not set the old sitebuilder hosting deployment settings, these should not be used for this product.
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $sitebuilderSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $sitebuilderSubscription->start_date;
        $invoiceItem->end_date = $sitebuilderSubscription->next_billing_date;
        $invoiceItem->period = $sitebuilderSubscription->contract_period;
        $invoiceItem->net_price = $sitebuilderSubscription->net_price;
        $invoiceItem->gross_price = $sitebuilderSubscription->gross_price;
        $invoiceItem->title = $sitebuilderSubscription->domain ?? $sitebuilderSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $sitebuilderSubscription->product->name, $sitebuilderSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $sitebuilderOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $sitebuilderOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function bronze(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_BRONZE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_BRONZE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'hosting-bronze.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->mail_only_provider_id = null;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->directadmin_customer_username = 'da-hosting-bronze-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function webBasic(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);

        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        //Addons
        $fancyInstallerProduct = $this->referenceRepo->get(ProductReference::ADD_ON_FANCY_INSTALLER, Product::class);
        $extraDatabaseProduct = $this->referenceRepo->get(ProductReference::ADD_ON_EXTRA_DB, Product::class);
        $extraStorageProduct = $this->referenceRepo->get(ProductReference::ADD_ON_EXTRA_STORAGE, Product::class);
        $fancyInstallerPrice = $this->referenceRepo->get(ProductReference::ADD_ON_FANCY_INSTALLER_PRICE, ProductPriceComponent::class);
        $extraDatabasePrice = $this->referenceRepo->get(ProductReference::ADD_ON_EXTRA_DB_PRICE, ProductPriceComponent::class);
        $extraStoragePrice = $this->referenceRepo->get(ProductReference::ADD_ON_EXTRA_STORAGE_PRICE, ProductPriceComponent::class);
        $servicePlusProduct = $this->referenceRepo->get(ProductReference::SERVICE_SERVICE_PLUS, Product::class);
        $servicePlusPrice = $this->referenceRepo->get(ProductReference::SERVICE_SERVICE_PLUS_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price + $servicePlusPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'hosting-web-basic-addons.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        //Addons
        $fancyInstallerOrderLine = new OrderLineItem();
        $fancyInstallerOrderLine->order_id = $order->id;
        $fancyInstallerOrderLine->domain = null;
        $fancyInstallerOrderLine->product_uuid = $fancyInstallerProduct->uuid;
        $fancyInstallerOrderLine->product_name = $fancyInstallerProduct->name;
        $fancyInstallerOrderLine->status = OrderLineItemStatus::REGISTRATION;
        $fancyInstallerOrderLine->gross_price = $fancyInstallerPrice->price;
        $fancyInstallerOrderLine->net_price = $fancyInstallerPrice->price;
        $fancyInstallerOrderLine->billing_period = $fancyInstallerPrice->billing_period;
        $fancyInstallerOrderLine->contract_period = $fancyInstallerPrice->contract_period;
        $fancyInstallerOrderLine->should_invoice = true;
        $fancyInstallerOrderLine->save();

        $fancyInstallerSubscription = new Subscription();
        $fancyInstallerSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $fancyInstallerSubscription->uuid = Uuid::uuid4()->toString();
        $fancyInstallerSubscription->customer_id = $customer->id;
        $fancyInstallerSubscription->product_uuid = $fancyInstallerProduct->uuid;
        $fancyInstallerSubscription->domain = null;
        $fancyInstallerSubscription->parent_subscription_id = $hostingSubscription->id;
        $fancyInstallerSubscription->contract_period = $fancyInstallerPrice->contract_period;
        $fancyInstallerSubscription->billing_period = $fancyInstallerPrice->billing_period;
        $fancyInstallerSubscription->net_price = $fancyInstallerOrderLine->net_price;
        $fancyInstallerSubscription->gross_price = $fancyInstallerOrderLine->gross_price;
        $fancyInstallerSubscription->technical_status = TechnicalStatus::OK->value;
        $fancyInstallerSubscription->start_date = CarbonImmutable::yesterday();
        $fancyInstallerSubscription->next_billing_date = $fancyInstallerSubscription->start_date->addMonths($fancyInstallerSubscription->billing_period);
        $fancyInstallerSubscription->end_date = $fancyInstallerSubscription->start_date->addMonths($fancyInstallerSubscription->contract_period);
        $fancyInstallerSubscription->save();

        $fancyInstallerSubscriptionPrice = new SubscriptionPrice();
        $fancyInstallerSubscriptionPrice->subscription_id = $fancyInstallerSubscription->id;
        $fancyInstallerSubscriptionPrice->valid_from = $fancyInstallerSubscription->start_date;
        $fancyInstallerSubscriptionPrice->net_price = $fancyInstallerSubscription->net_price;
        $fancyInstallerSubscriptionPrice->save();

        $fancyInstallerSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $fancyInstallerSubscriptionPriceComponent->subscription_price_id = $fancyInstallerSubscriptionPrice->id;
        $fancyInstallerSubscriptionPriceComponent->type = $fancyInstallerPrice->type;
        $fancyInstallerSubscriptionPriceComponent->percentage_discount = null;
        $fancyInstallerSubscriptionPriceComponent->fixed_discount = null;
        $fancyInstallerSubscriptionPriceComponent->fixed_price = $fancyInstallerSubscription->net_price;
        $fancyInstallerSubscriptionPriceComponent->new_price = $fancyInstallerSubscription->net_price;
        $fancyInstallerSubscriptionPriceComponent->order_applied = 1;
        $fancyInstallerSubscriptionPriceComponent->save();

        $fancyInstallerSubscription->subscription_price_id = $fancyInstallerSubscriptionPrice->id;
        $fancyInstallerSubscription->save();

        $fancyInstallerOrderLine->subscription_uuid = $fancyInstallerSubscription->uuid;
        $fancyInstallerOrderLine->save();

        $extraDatabaseOrderLine = new OrderLineItem();
        $extraDatabaseOrderLine->order_id = $order->id;
        $extraDatabaseOrderLine->domain = null;
        $extraDatabaseOrderLine->product_uuid = $extraDatabaseProduct->uuid;
        $extraDatabaseOrderLine->product_name = $extraDatabaseProduct->name;
        $extraDatabaseOrderLine->status = OrderLineItemStatus::REGISTRATION;
        $extraDatabaseOrderLine->gross_price = $extraDatabasePrice->price;
        $extraDatabaseOrderLine->net_price = $extraDatabasePrice->price;
        $extraDatabaseOrderLine->billing_period = $extraDatabasePrice->billing_period;
        $extraDatabaseOrderLine->contract_period = $extraDatabasePrice->contract_period;
        $extraDatabaseOrderLine->should_invoice = true;
        $extraDatabaseOrderLine->save();

        $extraDatabaseSubscription = new Subscription();
        $extraDatabaseSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $extraDatabaseSubscription->uuid = Uuid::uuid4()->toString();
        $extraDatabaseSubscription->customer_id = $customer->id;
        $extraDatabaseSubscription->product_uuid = $extraDatabaseProduct->uuid;
        $extraDatabaseSubscription->domain = null;
        $extraDatabaseSubscription->parent_subscription_id = $hostingSubscription->id;
        $extraDatabaseSubscription->contract_period = $extraDatabasePrice->contract_period;
        $extraDatabaseSubscription->billing_period = $extraDatabasePrice->billing_period;
        $extraDatabaseSubscription->net_price = $extraDatabaseOrderLine->net_price;
        $extraDatabaseSubscription->gross_price = $extraDatabaseOrderLine->gross_price;
        $extraDatabaseSubscription->technical_status = TechnicalStatus::OK->value;
        $extraDatabaseSubscription->start_date = CarbonImmutable::yesterday();
        $extraDatabaseSubscription->next_billing_date = $extraDatabaseSubscription->start_date->addMonths($extraDatabaseSubscription->billing_period);
        $extraDatabaseSubscription->end_date = $extraDatabaseSubscription->start_date->addMonths($extraDatabaseSubscription->contract_period);
        $extraDatabaseSubscription->save();

        $extraDatabaseSubscriptionPrice = new SubscriptionPrice();
        $extraDatabaseSubscriptionPrice->subscription_id = $extraDatabaseSubscription->id;
        $extraDatabaseSubscriptionPrice->valid_from = $extraDatabaseSubscription->start_date;
        $extraDatabaseSubscriptionPrice->net_price = $extraDatabaseSubscription->net_price;
        $extraDatabaseSubscriptionPrice->save();

        $extraDatabaseSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $extraDatabaseSubscriptionPriceComponent->subscription_price_id = $extraDatabaseSubscriptionPrice->id;
        $extraDatabaseSubscriptionPriceComponent->type = $extraDatabasePrice->type;
        $extraDatabaseSubscriptionPriceComponent->percentage_discount = null;
        $extraDatabaseSubscriptionPriceComponent->fixed_discount = null;
        $extraDatabaseSubscriptionPriceComponent->fixed_price = $extraDatabaseSubscription->net_price;
        $extraDatabaseSubscriptionPriceComponent->new_price = $extraDatabaseSubscription->net_price;
        $extraDatabaseSubscriptionPriceComponent->order_applied = 1;
        $extraDatabaseSubscriptionPriceComponent->save();

        $extraDatabaseSubscription->subscription_price_id = $extraDatabaseSubscriptionPrice->id;
        $extraDatabaseSubscription->save();

        $extraDatabaseOrderLine->subscription_uuid = $extraDatabaseSubscription->uuid;
        $extraDatabaseOrderLine->save();

        $extraStorageOrderLine = new OrderLineItem();
        $extraStorageOrderLine->order_id = $order->id;
        $extraStorageOrderLine->domain = null;
        $extraStorageOrderLine->product_uuid = $extraStorageProduct->uuid;
        $extraStorageOrderLine->product_name = $extraStorageProduct->name;
        $extraStorageOrderLine->status = OrderLineItemStatus::REGISTRATION;
        $extraStorageOrderLine->gross_price = $extraStoragePrice->price;
        $extraStorageOrderLine->net_price = $extraStoragePrice->price;
        $extraStorageOrderLine->billing_period = $extraStoragePrice->billing_period;
        $extraStorageOrderLine->contract_period = $extraStoragePrice->contract_period;
        $extraStorageOrderLine->should_invoice = true;
        $extraStorageOrderLine->save();

        $extraStorageSubscription = new Subscription();
        $extraStorageSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $extraStorageSubscription->uuid = Uuid::uuid4()->toString();
        $extraStorageSubscription->customer_id = $customer->id;
        $extraStorageSubscription->product_uuid = $extraStorageProduct->uuid;
        $extraStorageSubscription->domain = null;
        $extraStorageSubscription->parent_subscription_id = $hostingSubscription->id;
        $extraStorageSubscription->contract_period = $extraStoragePrice->contract_period;
        $extraStorageSubscription->billing_period = $extraStoragePrice->billing_period;
        $extraStorageSubscription->net_price = $extraStorageOrderLine->net_price;
        $extraStorageSubscription->gross_price = $extraStorageOrderLine->gross_price;
        $extraStorageSubscription->technical_status = TechnicalStatus::OK->value;
        $extraStorageSubscription->start_date = CarbonImmutable::yesterday();
        $extraStorageSubscription->next_billing_date = $extraStorageSubscription->start_date->addMonths($extraStorageSubscription->billing_period);
        $extraStorageSubscription->end_date = $extraStorageSubscription->start_date->addMonths($extraStorageSubscription->contract_period);
        $extraStorageSubscription->save();

        $extraStorageSubscriptionPrice = new SubscriptionPrice();
        $extraStorageSubscriptionPrice->subscription_id = $extraStorageSubscription->id;
        $extraStorageSubscriptionPrice->valid_from = $extraStorageSubscription->start_date;
        $extraStorageSubscriptionPrice->net_price = $extraStorageSubscription->net_price;
        $extraStorageSubscriptionPrice->save();

        $extraStorageSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $extraStorageSubscriptionPriceComponent->subscription_price_id = $extraStorageSubscriptionPrice->id;
        $extraStorageSubscriptionPriceComponent->type = $extraStoragePrice->type;
        $extraStorageSubscriptionPriceComponent->percentage_discount = null;
        $extraStorageSubscriptionPriceComponent->fixed_discount = null;
        $extraStorageSubscriptionPriceComponent->fixed_price = $extraStorageSubscription->net_price;
        $extraStorageSubscriptionPriceComponent->new_price = $extraStorageSubscription->net_price;
        $extraStorageSubscriptionPriceComponent->order_applied = 1;
        $extraStorageSubscriptionPriceComponent->save();

        $extraStorageSubscription->subscription_price_id = $extraStorageSubscriptionPrice->id;
        $extraStorageSubscription->save();

        $extraStorageOrderLine->subscription_uuid = $extraStorageSubscription->uuid;
        $extraStorageOrderLine->save();

        $servicePlusOrderItem = new OrderLineItem();
        $servicePlusOrderItem->order_id = $order->id;
        $servicePlusOrderItem->product_uuid = $servicePlusProduct->uuid;
        $servicePlusOrderItem->product_name = $servicePlusProduct->name;
        $servicePlusOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $servicePlusOrderItem->gross_price = $servicePlusPrice->price;
        $servicePlusOrderItem->net_price = $servicePlusPrice->price;
        $servicePlusOrderItem->billing_period = $servicePlusPrice->billing_period;
        $servicePlusOrderItem->contract_period = $servicePlusPrice->contract_period;
        $servicePlusOrderItem->should_invoice = true;
        $servicePlusOrderItem->save();

        $servicePlusSubscription = new Subscription();
        $servicePlusSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $servicePlusSubscription->uuid = Uuid::uuid4()->toString();
        $servicePlusSubscription->customer_id = $customer->id;
        $servicePlusSubscription->product_uuid = $servicePlusProduct->uuid;
        $servicePlusSubscription->domain = $servicePlusOrderItem->domain;
        $servicePlusSubscription->contract_period = $servicePlusPrice->contract_period;
        $servicePlusSubscription->billing_period = $servicePlusPrice->billing_period;
        $servicePlusSubscription->net_price = $servicePlusOrderItem->net_price;
        $servicePlusSubscription->gross_price = $servicePlusOrderItem->gross_price;
        $servicePlusSubscription->technical_status = TechnicalStatus::OK->value;
        $servicePlusSubscription->start_date = CarbonImmutable::yesterday();
        $servicePlusSubscription->next_billing_date = $servicePlusSubscription->start_date->addMonths($servicePlusSubscription->billing_period);
        $servicePlusSubscription->end_date = $servicePlusSubscription->start_date->addMonths($servicePlusSubscription->contract_period);
        $servicePlusSubscription->save();

        $servicePlusSubscriptionPrice = new SubscriptionPrice();
        $servicePlusSubscriptionPrice->subscription_id = $servicePlusSubscription->id;
        $servicePlusSubscriptionPrice->valid_from = $servicePlusSubscription->start_date;
        $servicePlusSubscriptionPrice->net_price = $servicePlusSubscription->net_price;
        $servicePlusSubscriptionPrice->save();

        $servicePlusSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $servicePlusSubscriptionPriceComponent->subscription_price_id = $servicePlusSubscriptionPrice->id;
        $servicePlusSubscriptionPriceComponent->type = $servicePlusPrice->type;
        $servicePlusSubscriptionPriceComponent->percentage_discount = null;
        $servicePlusSubscriptionPriceComponent->fixed_discount = null;
        $servicePlusSubscriptionPriceComponent->fixed_price = $servicePlusSubscription->net_price;
        $servicePlusSubscriptionPriceComponent->new_price = $servicePlusSubscription->net_price;
        $servicePlusSubscriptionPriceComponent->order_applied = 1;
        $servicePlusSubscriptionPriceComponent->save();

        $servicePlusSubscription->subscription_price_id = $servicePlusSubscriptionPrice->id;
        $servicePlusSubscription->save();

        $servicePlusOrderItem->subscription_uuid = $servicePlusSubscription->uuid;
        $servicePlusOrderItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->directadmin_customer_username = 'da-hosting-bronze-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function webBasicWpWithOneTimeService(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $otsProduct = $this->referenceRepo->get(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN, Product::class);
        $otsPrice = $this->referenceRepo->get(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebBasicWpOrderLineItem = new OrderLineItem();
        $hostingWebBasicWpOrderLineItem->order_id = $order->id;
        $hostingWebBasicWpOrderLineItem->domain = 'hosting-web-basic-wp.nl';
        $hostingWebBasicWpOrderLineItem->product_uuid = $product->uuid;
        $hostingWebBasicWpOrderLineItem->product_name = $product->name;
        $hostingWebBasicWpOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebBasicWpOrderLineItem->gross_price = $price->price;
        $hostingWebBasicWpOrderLineItem->net_price = $price->price;
        $hostingWebBasicWpOrderLineItem->billing_period = $price->billing_period;
        $hostingWebBasicWpOrderLineItem->contract_period = $price->contract_period;
        $hostingWebBasicWpOrderLineItem->should_invoice = true;
        $hostingWebBasicWpOrderLineItem->save();

        $hostingWebBasicWpSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebBasicWpOrderLineItem
        );

        $hostingWebBasicWpOrderLineItem->subscription_uuid = $hostingWebBasicWpSubscription->uuid;
        $hostingWebBasicWpOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebBasicWpSubscription, $server, $provider, 'direct-admin-web-basic-wp');

        $this->createInvoiceItem($hostingWebBasicWpSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $hostingWebBasicWpOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebBasicWpOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $otsProduct->uuid;
        $orderItem->product_name = $otsProduct->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $otsPrice->price;
        $orderItem->net_price = $otsPrice->price;
        $orderItem->billing_period = $otsPrice->billing_period;
        $orderItem->contract_period = $otsPrice->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $oneTimeService = new OneTimeService();
        $oneTimeService->uuid = Uuid::uuid4();
        $oneTimeService->subscription_id = $hostingWebBasicWpSubscription->id;
        $oneTimeService->customer_id = $customer->id;
        $oneTimeService->product_id = $otsProduct->id;
        $oneTimeService->amount = 1;
        $oneTimeService->discount_percentage = 10;
        $oneTimeService->gross_price = 650000;
        $oneTimeService->execution_date = CarbonImmutable::yesterday();
        $oneTimeService->status = OneTimeServiceStatus::IN_PROGRESS;
        $oneTimeService->save();

        $invoice = new Invoice();
        $invoice->customer_id = $customer->id;
        $invoice->product_id = $otsProduct->id;
        $invoice->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoice->vat_rate = $customer->vat_rate ?? 0;
        $invoice->ledger_code = $otsProduct->productGroup->ledger_code;
        $invoice->paid = false;
        $invoice->start_date = CarbonImmutable::yesterday();
        $invoice->end_date = CarbonImmutable::yesterday();
        $invoice->period = 1;
        $invoice->gross_price = 650000;
        $invoice->net_price = $otsPrice->price;
        $invoice->title = $otsProduct->name;
        $invoice->description = $otsProduct->name;
        $invoice->group_label = null;
        $invoice->type = InvoiceLine::TYPE_DEFAULT;
        $invoice->prepaid_reference = null;
        $invoice->save();

        $oneTimeService->invoices()->attach($invoice->id);
    }

    /**
     * An order carrying a one time service line item that has not been processed
     * yet, so the "process line item" action is available on it in Compass.
     *
     * The hosting line item it hangs under is processed on purpose: creating the
     * one time service needs the parent line item to already have a subscription.
     */
    private function webBasicWpWithUnprocessedOneTimeService(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_WP_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $otsProduct = $this->referenceRepo->get(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN, Product::class);
        $otsPrice = $this->referenceRepo->get(ProductReference::ONE_TIME_SERVICE_WORDPRESS_DESIGN_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::IN_PROGRESS;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $otsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = false;
        $order->save();

        $hostingOrderLineItem = new OrderLineItem();
        $hostingOrderLineItem->order_id = $order->id;
        $hostingOrderLineItem->domain = 'hosting-web-basic-wp-unprocessed-ots.nl';
        $hostingOrderLineItem->product_uuid = $product->uuid;
        $hostingOrderLineItem->product_name = $product->name;
        $hostingOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderLineItem->gross_price = $price->price;
        $hostingOrderLineItem->net_price = $price->price;
        $hostingOrderLineItem->billing_period = $price->billing_period;
        $hostingOrderLineItem->contract_period = $price->contract_period;
        $hostingOrderLineItem->processed_at = CarbonImmutable::yesterday();
        $hostingOrderLineItem->should_invoice = true;
        $hostingOrderLineItem->save();

        $hostingSubscription = $this->createSubscription($customer, $product, $price, $hostingOrderLineItem);

        $hostingOrderLineItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderLineItem->save();

        $this->createDirectAdminHostingDeployment(
            $hostingSubscription,
            $server,
            $provider,
            'direct-admin-web-basic-wp-unprocessed-ots'
        );

        $this->createInvoiceItem($hostingSubscription, $customer, $product);

        // No OneTimeService and no processed_at: this is what the action creates.
        $otsOrderLineItem = new OrderLineItem();
        $otsOrderLineItem->order_id = $order->id;
        $otsOrderLineItem->parent_id = $hostingOrderLineItem->id;
        $otsOrderLineItem->product_uuid = $otsProduct->uuid;
        $otsOrderLineItem->product_name = $otsProduct->name;
        $otsOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $otsOrderLineItem->gross_price = $otsPrice->price;
        $otsOrderLineItem->net_price = $otsPrice->price;
        $otsOrderLineItem->billing_period = $otsPrice->billing_period;
        $otsOrderLineItem->contract_period = $otsPrice->contract_period;
        $otsOrderLineItem->should_invoice = true;
        $otsOrderLineItem->save();
    }

    private function webBasicExpired(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);

        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'hosting-web-basic-expired.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $expiryDate = CarbonImmutable::yesterday();
        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::EXPIRED->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = $expiryDate->subMonths($hostingSubscription->contract_period);
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $expiryDate;
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->directadmin_customer_username = 'da-hosting-bronze-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::EXPIRED->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = $expiryDate->subMonths($domainSubscription->contract_period);
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $expiryDate;
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::EXPIRED->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $expiryDate->subMonths($dnsSubscription->contract_period);
        $dnsSubscription->next_billing_date = $dnsSubscription->start_date->addMonths($dnsSubscription->billing_period);
        $dnsSubscription->end_date = $expiryDate;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function webGrow(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebGrowOrderLineItem = new OrderLineItem();
        $hostingWebGrowOrderLineItem->order_id = $order->id;
        $hostingWebGrowOrderLineItem->domain = 'hosting-web-grow.nl';
        $hostingWebGrowOrderLineItem->product_uuid = $product->uuid;
        $hostingWebGrowOrderLineItem->product_name = $product->name;
        $hostingWebGrowOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebGrowOrderLineItem->gross_price = $price->price;
        $hostingWebGrowOrderLineItem->net_price = $price->price;
        $hostingWebGrowOrderLineItem->billing_period = $price->billing_period;
        $hostingWebGrowOrderLineItem->contract_period = $price->contract_period;
        $hostingWebGrowOrderLineItem->should_invoice = true;
        $hostingWebGrowOrderLineItem->save();

        $hostingWebGrowSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebGrowOrderLineItem
        );

        $hostingWebGrowOrderLineItem->subscription_uuid = $hostingWebGrowSubscription->uuid;
        $hostingWebGrowOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebGrowSubscription, $server, $provider, 'direct-admin-web-grow');

        $this->createInvoiceItem($hostingWebGrowSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $hostingWebGrowOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebGrowOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webStart(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_START_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebStartOrderLineItem = new OrderLineItem();
        $hostingWebStartOrderLineItem->order_id = $order->id;
        $hostingWebStartOrderLineItem->domain = 'hosting-web-start.nl';
        $hostingWebStartOrderLineItem->product_uuid = $product->uuid;
        $hostingWebStartOrderLineItem->product_name = $product->name;
        $hostingWebStartOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebStartOrderLineItem->gross_price = $price->price;
        $hostingWebStartOrderLineItem->net_price = $price->price;
        $hostingWebStartOrderLineItem->billing_period = $price->billing_period;
        $hostingWebStartOrderLineItem->contract_period = $price->contract_period;
        $hostingWebStartOrderLineItem->should_invoice = true;
        $hostingWebStartOrderLineItem->save();

        $hostingWebStartSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebStartOrderLineItem
        );

        $hostingWebStartOrderLineItem->subscription_uuid = $hostingWebStartSubscription->uuid;
        $hostingWebStartOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebStartSubscription, $server, $provider, 'da-hosting-web-start-username');

        $this->createInvoiceItem($hostingWebStartSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebStartOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebStartOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webPlus(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_PLUS_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebPlusOrderLineItem = new OrderLineItem();
        $hostingWebPlusOrderLineItem->order_id = $order->id;
        $hostingWebPlusOrderLineItem->domain = 'hosting-web-plus.nl';
        $hostingWebPlusOrderLineItem->product_uuid = $product->uuid;
        $hostingWebPlusOrderLineItem->product_name = $product->name;
        $hostingWebPlusOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebPlusOrderLineItem->gross_price = $price->price;
        $hostingWebPlusOrderLineItem->net_price = $price->price;
        $hostingWebPlusOrderLineItem->billing_period = $price->billing_period;
        $hostingWebPlusOrderLineItem->contract_period = $price->contract_period;
        $hostingWebPlusOrderLineItem->should_invoice = true;
        $hostingWebPlusOrderLineItem->save();

        $hostingWebPlusSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebPlusOrderLineItem
        );

        $hostingWebPlusOrderLineItem->subscription_uuid = $hostingWebPlusSubscription->uuid;
        $hostingWebPlusOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebPlusSubscription, $server, $provider, 'da-hosting-web-plus-username');

        $this->createInvoiceItem($hostingWebPlusSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $hostingWebPlusOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebPlusOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function mailOnlyBasicDirectAdmin(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_BASIC_DIRECT_ADMIN_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingMailOnlyBasicDirectAdminOrderLineItem = new OrderLineItem();
        $hostingMailOnlyBasicDirectAdminOrderLineItem->order_id = $order->id;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->domain = 'hosting-mail-only-directadmin.nl';
        $hostingMailOnlyBasicDirectAdminOrderLineItem->product_uuid = $product->uuid;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->product_name = $product->name;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->gross_price = $price->price;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->net_price = $price->price;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->billing_period = $price->billing_period;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->contract_period = $price->contract_period;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->should_invoice = true;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->save();

        $hostingMailOnlyBasicDirectAdminSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingMailOnlyBasicDirectAdminOrderLineItem
        );

        $hostingMailOnlyBasicDirectAdminOrderLineItem->subscription_uuid = $hostingMailOnlyBasicDirectAdminSubscription->uuid;
        $hostingMailOnlyBasicDirectAdminOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingMailOnlyBasicDirectAdminSubscription, $server, $provider, 'da-hosting-mail-only-basic-username');

        $this->createInvoiceItem($hostingMailOnlyBasicDirectAdminSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyBasicDirectAdminOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyBasicDirectAdminOrderLineItem,
            $dnsProduct,
            $dnsPrice
        );

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function premium(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_PREMIUM, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_PREMIUM_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingPremiumOrderLineItem = new OrderLineItem();
        $hostingPremiumOrderLineItem->order_id = $order->id;
        $hostingPremiumOrderLineItem->domain = 'hosting-premium.nl';
        $hostingPremiumOrderLineItem->product_uuid = $product->uuid;
        $hostingPremiumOrderLineItem->product_name = $product->name;
        $hostingPremiumOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingPremiumOrderLineItem->gross_price = $price->price;
        $hostingPremiumOrderLineItem->net_price = $price->price;
        $hostingPremiumOrderLineItem->billing_period = $price->billing_period;
        $hostingPremiumOrderLineItem->contract_period = $price->contract_period;
        $hostingPremiumOrderLineItem->should_invoice = true;
        $hostingPremiumOrderLineItem->save();

        $hostingPremiumSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingPremiumOrderLineItem
        );

        $hostingPremiumOrderLineItem->subscription_uuid = $hostingPremiumSubscription->uuid;
        $hostingPremiumOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingPremiumSubscription, $server, $provider, 'direct-admin-premium');

        $this->createInvoiceItem($hostingPremiumSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $hostingPremiumOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingPremiumOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function mailOnly(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_REGISTRATION_PRICE, ProductPriceComponent::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingMailOnlyOrderLineItem = new OrderLineItem();
        $hostingMailOnlyOrderLineItem->order_id = $order->id;
        $hostingMailOnlyOrderLineItem->domain = 'hosting-mail-only.nl';
        $hostingMailOnlyOrderLineItem->product_uuid = $product->uuid;
        $hostingMailOnlyOrderLineItem->product_name = $product->name;
        $hostingMailOnlyOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingMailOnlyOrderLineItem->gross_price = $price->price;
        $hostingMailOnlyOrderLineItem->net_price = $price->price;
        $hostingMailOnlyOrderLineItem->billing_period = $price->billing_period;
        $hostingMailOnlyOrderLineItem->contract_period = $price->contract_period;
        $hostingMailOnlyOrderLineItem->should_invoice = true;
        $hostingMailOnlyOrderLineItem->save();

        $hostingMailOnlySubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingMailOnlyOrderLineItem
        );

        $hostingMailOnlyOrderLineItem->subscription_uuid = $hostingMailOnlySubscription->uuid;
        $hostingMailOnlyOrderLineItem->save();

        $this->createDirectAdminMailOnlyHostingDeployment($hostingMailOnlySubscription, $server, 'direct-admin-mail-only');

        $this->createInvoiceItem($hostingMailOnlySubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $hostingMailOnlyOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingMailOnlyOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function wordpressToolkit(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WORDPRESS_TOOLKIT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WORDPRESS_TOOLKIT_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_PLESK, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $wordpressToolkitOrderLineItem = new OrderLineItem();
        $wordpressToolkitOrderLineItem->order_id = $order->id;
        $wordpressToolkitOrderLineItem->domain = 'hosting-wordpress.nl';
        $wordpressToolkitOrderLineItem->product_uuid = $product->uuid;
        $wordpressToolkitOrderLineItem->product_name = $product->name;
        $wordpressToolkitOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $wordpressToolkitOrderLineItem->gross_price = $price->price;
        $wordpressToolkitOrderLineItem->net_price = $price->price;
        $wordpressToolkitOrderLineItem->billing_period = $price->billing_period;
        $wordpressToolkitOrderLineItem->contract_period = $price->contract_period;
        $wordpressToolkitOrderLineItem->should_invoice = true;
        $wordpressToolkitOrderLineItem->save();

        $hostingWebPlusSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $wordpressToolkitOrderLineItem
        );

        $wordpressToolkitOrderLineItem->subscription_uuid = $hostingWebPlusSubscription->uuid;
        $wordpressToolkitOrderLineItem->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingWebPlusSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->mail_only_provider_id = null;
        $hostingDeployment->plesk_customer_username = 'plesk-hosting-wordpress-toolkit-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingWebPlusSubscription->start_date->addHour();
        $hostingDeployment->wp_installation_id = 1;
        $hostingDeployment->save();

        $this->createInvoiceItem($hostingWebPlusSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem($order, $wordpressToolkitOrderLineItem, $domainProduct, $domainPrice);

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $wordpressToolkitOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function hostingPlaceholder(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_PLACEHOLDER, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_PLACEHOLDER_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_PLACEHOLDER, Provider::class);

        $hostingPlaceHolderSubscription = new Subscription();
        $hostingPlaceHolderSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingPlaceHolderSubscription->uuid = Uuid::uuid4()->toString();
        $hostingPlaceHolderSubscription->customer_id = $customer->id;
        $hostingPlaceHolderSubscription->product_uuid = $product->uuid;
        $hostingPlaceHolderSubscription->domain = 'hosting-placeholder.nl';
        $hostingPlaceHolderSubscription->contract_period = $price->contract_period;
        $hostingPlaceHolderSubscription->billing_period = $price->billing_period;
        $hostingPlaceHolderSubscription->net_price = $price->price;
        $hostingPlaceHolderSubscription->gross_price = $price->price;
        $hostingPlaceHolderSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingPlaceHolderSubscription->start_date = CarbonImmutable::yesterday();
        $hostingPlaceHolderSubscription->next_billing_date = $hostingPlaceHolderSubscription->start_date->addMonths($hostingPlaceHolderSubscription->billing_period);
        $hostingPlaceHolderSubscription->end_date = $hostingPlaceHolderSubscription->start_date->addMonths($hostingPlaceHolderSubscription->contract_period);
        $hostingPlaceHolderSubscription->save();

        $hostingPlaceHolderSubscriptionPrice = new SubscriptionPrice();
        $hostingPlaceHolderSubscriptionPrice->subscription_id = $hostingPlaceHolderSubscription->id;
        $hostingPlaceHolderSubscriptionPrice->valid_from = $hostingPlaceHolderSubscription->start_date;
        $hostingPlaceHolderSubscriptionPrice->net_price = $hostingPlaceHolderSubscription->net_price;
        $hostingPlaceHolderSubscriptionPrice->save();

        $hostingPlaceHolderSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingPlaceHolderSubscriptionPriceComponent->subscription_price_id = $hostingPlaceHolderSubscriptionPrice->id;
        $hostingPlaceHolderSubscriptionPriceComponent->type = $price->type;
        $hostingPlaceHolderSubscriptionPriceComponent->percentage_discount = null;
        $hostingPlaceHolderSubscriptionPriceComponent->fixed_discount = null;
        $hostingPlaceHolderSubscriptionPriceComponent->fixed_price = $hostingPlaceHolderSubscription->net_price;
        $hostingPlaceHolderSubscriptionPriceComponent->new_price = $hostingPlaceHolderSubscription->net_price;
        $hostingPlaceHolderSubscriptionPriceComponent->order_applied = 1;
        $hostingPlaceHolderSubscriptionPriceComponent->save();

        $hostingPlaceHolderSubscription->subscription_price_id = $hostingPlaceHolderSubscriptionPrice->id;
        $hostingPlaceHolderSubscription->save();

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingPlaceHolderSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingPlaceHolderSubscription->start_date->addHour();
        $hostingDeployment->save();

        $this->createInvoiceItem($hostingPlaceHolderSubscription, $customer, $product);
    }

    private function mailOnlyGrowPlesk(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_GROW_PLESK_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_MAIL_ONLY_PLESK, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingMailOnlyPleskOrderLineItem = new OrderLineItem();
        $hostingMailOnlyPleskOrderLineItem->order_id = $order->id;
        $hostingMailOnlyPleskOrderLineItem->domain = 'hosting-mail-only-plesk.nl';
        $hostingMailOnlyPleskOrderLineItem->product_uuid = $product->uuid;
        $hostingMailOnlyPleskOrderLineItem->product_name = $product->name;
        $hostingMailOnlyPleskOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingMailOnlyPleskOrderLineItem->gross_price = $price->price;
        $hostingMailOnlyPleskOrderLineItem->net_price = $price->price;
        $hostingMailOnlyPleskOrderLineItem->billing_period = $price->billing_period;
        $hostingMailOnlyPleskOrderLineItem->contract_period = $price->contract_period;
        $hostingMailOnlyPleskOrderLineItem->should_invoice = true;
        $hostingMailOnlyPleskOrderLineItem->save();

        $hostingMailOnlyPleskSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingMailOnlyPleskOrderLineItem
        );

        $hostingMailOnlyPleskOrderLineItem->subscription_uuid = $hostingMailOnlyPleskSubscription->uuid;
        $hostingMailOnlyPleskOrderLineItem->save();

        $this->createPleskHostingDeployment($hostingMailOnlyPleskSubscription, $server, $provider, 'plesk-hosting-mail-only-username');

        $this->createInvoiceItem($hostingMailOnlyPleskSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyPleskOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingMailOnlyPleskOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function mailOnlyStartPlesk(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_START_PLESK, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_START_PLESK_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_MAIL_ONLY_PLESK, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingMailOnlyStartOrderLineItem = new OrderLineItem();
        $hostingMailOnlyStartOrderLineItem->order_id = $order->id;
        $hostingMailOnlyStartOrderLineItem->domain = 'hosting-mail-only-start-plesk.nl';
        $hostingMailOnlyStartOrderLineItem->product_uuid = $product->uuid;
        $hostingMailOnlyStartOrderLineItem->product_name = $product->name;
        $hostingMailOnlyStartOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingMailOnlyStartOrderLineItem->gross_price = $price->price;
        $hostingMailOnlyStartOrderLineItem->net_price = $price->price;
        $hostingMailOnlyStartOrderLineItem->billing_period = $price->billing_period;
        $hostingMailOnlyStartOrderLineItem->contract_period = $price->contract_period;
        $hostingMailOnlyStartOrderLineItem->should_invoice = true;
        $hostingMailOnlyStartOrderLineItem->save();

        $hostingMailOnlyStartSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingMailOnlyStartOrderLineItem
        );

        $hostingMailOnlyStartOrderLineItem->subscription_uuid = $hostingMailOnlyStartSubscription->uuid;
        $hostingMailOnlyStartOrderLineItem->save();

        $this->createPleskHostingDeployment($hostingMailOnlyStartSubscription, $server, $provider, 'plesk-hosting-mail-only-start-username');

        $this->createInvoiceItem($hostingMailOnlyStartSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyStartOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingMailOnlyStartOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function mailOnlyPlusPlesk(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PLUS_PLESK_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_MAIL_ONLY_PLESK, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingMailOnlyPlusPleskOrderLineItem = new OrderLineItem();
        $hostingMailOnlyPlusPleskOrderLineItem->order_id = $order->id;
        $hostingMailOnlyPlusPleskOrderLineItem->domain = 'hosting-mail-plus-plesk.nl';
        $hostingMailOnlyPlusPleskOrderLineItem->product_uuid = $product->uuid;
        $hostingMailOnlyPlusPleskOrderLineItem->product_name = $product->name;
        $hostingMailOnlyPlusPleskOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingMailOnlyPlusPleskOrderLineItem->gross_price = $price->price;
        $hostingMailOnlyPlusPleskOrderLineItem->net_price = $price->price;
        $hostingMailOnlyPlusPleskOrderLineItem->billing_period = $price->billing_period;
        $hostingMailOnlyPlusPleskOrderLineItem->contract_period = $price->contract_period;
        $hostingMailOnlyPlusPleskOrderLineItem->should_invoice = true;
        $hostingMailOnlyPlusPleskOrderLineItem->save();

        $hostingMailOnlyPlusPleskSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingMailOnlyPlusPleskOrderLineItem
        );

        $hostingMailOnlyPlusPleskOrderLineItem->subscription_uuid = $hostingMailOnlyPlusPleskSubscription->uuid;
        $hostingMailOnlyPlusPleskOrderLineItem->save();

        $this->createPleskHostingDeployment($hostingMailOnlyPlusPleskSubscription, $server, $provider, 'plesk-hosting-mail-only-plus-username');

        $this->createInvoiceItem($hostingMailOnlyPlusPleskSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyPlusPleskOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingMailOnlyPlusPleskOrderLineItem,
            $dnsProduct,
            $dnsPrice
        );

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyMini(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_MINI, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_MINI_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyMiniOrderLineItem = new OrderLineItem();
        $hostingWebOnlyMiniOrderLineItem->order_id = $order->id;
        $hostingWebOnlyMiniOrderLineItem->domain = 'hosting-web-only-mini.nl';
        $hostingWebOnlyMiniOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyMiniOrderLineItem->product_name = $product->name;
        $hostingWebOnlyMiniOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyMiniOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyMiniOrderLineItem->net_price = $price->price;
        $hostingWebOnlyMiniOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyMiniOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyMiniOrderLineItem->should_invoice = true;
        $hostingWebOnlyMiniOrderLineItem->save();

        $hostingWebOnlyMiniSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyMiniOrderLineItem
        );

        $hostingWebOnlyMiniOrderLineItem->subscription_uuid = $hostingWebOnlyMiniSubscription->uuid;
        $hostingWebOnlyMiniOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyMiniSubscription, $server, $provider, 'direct-admin-web-only-mini');

        $this->createInvoiceItem($hostingWebOnlyMiniSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyMiniOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyMiniOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyBasic(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyBasicOrderLineItem = new OrderLineItem();
        $hostingWebOnlyBasicOrderLineItem->order_id = $order->id;
        $hostingWebOnlyBasicOrderLineItem->domain = 'hosting-web-only-basic.nl';
        $hostingWebOnlyBasicOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyBasicOrderLineItem->product_name = $product->name;
        $hostingWebOnlyBasicOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyBasicOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyBasicOrderLineItem->net_price = $price->price;
        $hostingWebOnlyBasicOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyBasicOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyBasicOrderLineItem->should_invoice = true;
        $hostingWebOnlyBasicOrderLineItem->save();

        $hostingWebOnlyBasicSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyBasicOrderLineItem
        );

        $hostingWebOnlyBasicOrderLineItem->subscription_uuid = $hostingWebOnlyBasicSubscription->uuid;
        $hostingWebOnlyBasicOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyBasicSubscription, $server, $provider, 'direct-admin-web-only-basic');

        $this->createInvoiceItem($hostingWebOnlyBasicSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyBasicOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyBasicOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyBasicWp(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_WP, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_BASIC_WP_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyBasicOrderLineItem = new OrderLineItem();
        $hostingWebOnlyBasicOrderLineItem->order_id = $order->id;
        $hostingWebOnlyBasicOrderLineItem->domain = 'hosting-web-only-basic-wp.nl';
        $hostingWebOnlyBasicOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyBasicOrderLineItem->product_name = $product->name;
        $hostingWebOnlyBasicOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyBasicOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyBasicOrderLineItem->net_price = $price->price;
        $hostingWebOnlyBasicOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyBasicOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyBasicOrderLineItem->should_invoice = true;
        $hostingWebOnlyBasicOrderLineItem->save();

        $hostingWebOnlyBasicSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyBasicOrderLineItem
        );

        $hostingWebOnlyBasicOrderLineItem->subscription_uuid = $hostingWebOnlyBasicSubscription->uuid;
        $hostingWebOnlyBasicOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyBasicSubscription, $server, $provider, 'direct-admin-web-only-basic-wp');

        $this->createInvoiceItem($hostingWebOnlyBasicSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyBasicOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyBasicOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyGrow(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_GROW_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyGrowOrderLineItem = new OrderLineItem();
        $hostingWebOnlyGrowOrderLineItem->order_id = $order->id;
        $hostingWebOnlyGrowOrderLineItem->domain = 'hosting-web-only-grow.nl';
        $hostingWebOnlyGrowOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyGrowOrderLineItem->product_name = $product->name;
        $hostingWebOnlyGrowOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyGrowOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyGrowOrderLineItem->net_price = $price->price;
        $hostingWebOnlyGrowOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyGrowOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyGrowOrderLineItem->should_invoice = true;
        $hostingWebOnlyGrowOrderLineItem->save();

        $hostingWebOnlyGrowSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyGrowOrderLineItem
        );

        $hostingWebOnlyGrowOrderLineItem->subscription_uuid = $hostingWebOnlyGrowSubscription->uuid;
        $hostingWebOnlyGrowOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyGrowSubscription, $server, $provider, 'direct-admin-web-only-grow');

        $this->createInvoiceItem($hostingWebOnlyGrowSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyGrowOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyGrowOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyStart(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_START_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyStartOrderLineItem = new OrderLineItem();
        $hostingWebOnlyStartOrderLineItem->order_id = $order->id;
        $hostingWebOnlyStartOrderLineItem->domain = 'hosting-web-only-start.nl';
        $hostingWebOnlyStartOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyStartOrderLineItem->product_name = $product->name;
        $hostingWebOnlyStartOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyStartOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyStartOrderLineItem->net_price = $price->price;
        $hostingWebOnlyStartOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyStartOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyStartOrderLineItem->should_invoice = true;
        $hostingWebOnlyStartOrderLineItem->save();

        $hostingWebOnlyStartSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyStartOrderLineItem
        );

        $hostingWebOnlyStartOrderLineItem->subscription_uuid = $hostingWebOnlyStartSubscription->uuid;
        $hostingWebOnlyStartOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyStartSubscription, $server, $provider, 'da-hosting-web-only-start-username');

        $this->createInvoiceItem($hostingWebOnlyStartSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyStartOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyStartOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function webOnlyPlus(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_ONLY_PLUS_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingWebOnlyPlusOrderLineItem = new OrderLineItem();
        $hostingWebOnlyPlusOrderLineItem->order_id = $order->id;
        $hostingWebOnlyPlusOrderLineItem->domain = 'hosting-web-only-plus.nl';
        $hostingWebOnlyPlusOrderLineItem->product_uuid = $product->uuid;
        $hostingWebOnlyPlusOrderLineItem->product_name = $product->name;
        $hostingWebOnlyPlusOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingWebOnlyPlusOrderLineItem->gross_price = $price->price;
        $hostingWebOnlyPlusOrderLineItem->net_price = $price->price;
        $hostingWebOnlyPlusOrderLineItem->billing_period = $price->billing_period;
        $hostingWebOnlyPlusOrderLineItem->contract_period = $price->contract_period;
        $hostingWebOnlyPlusOrderLineItem->should_invoice = true;
        $hostingWebOnlyPlusOrderLineItem->save();

        $hostingWebOnlyPlusSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingWebOnlyPlusOrderLineItem
        );

        $hostingWebOnlyPlusOrderLineItem->subscription_uuid = $hostingWebOnlyPlusSubscription->uuid;
        $hostingWebOnlyPlusOrderLineItem->save();

        $this->createDirectAdminHostingDeployment($hostingWebOnlyPlusSubscription, $server, $provider, 'direct-admin-web-only-plus');

        $this->createInvoiceItem($hostingWebOnlyPlusSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingWebOnlyPlusOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingWebOnlyPlusOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function redirect(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::REDIRECT_PAID_REDIRECT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::REDIRECT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingRedirectOrderLineItem = new OrderLineItem();
        $hostingRedirectOrderLineItem->order_id = $order->id;
        $hostingRedirectOrderLineItem->domain = 'hosting-redirect.nl';
        $hostingRedirectOrderLineItem->product_uuid = $product->uuid;
        $hostingRedirectOrderLineItem->product_name = $product->name;
        $hostingRedirectOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingRedirectOrderLineItem->gross_price = $price->price;
        $hostingRedirectOrderLineItem->net_price = $price->price;
        $hostingRedirectOrderLineItem->billing_period = $price->billing_period;
        $hostingRedirectOrderLineItem->contract_period = $price->contract_period;
        $hostingRedirectOrderLineItem->should_invoice = true;
        $hostingRedirectOrderLineItem->save();

        $hostingRedirectSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $hostingRedirectOrderLineItem
        );

        $hostingRedirectOrderLineItem->subscription_uuid = $hostingRedirectSubscription->uuid;
        $hostingRedirectOrderLineItem->save();

        $this->createInvoiceItem($hostingRedirectSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $hostingRedirectOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $hostingRedirectOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function caddyRedirect(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::REDIRECT_CADDY_REDIRECT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::REDIRECT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $caddyRedirectOrderLineItem = new OrderLineItem();
        $caddyRedirectOrderLineItem->order_id = $order->id;
        $caddyRedirectOrderLineItem->domain = 'caddy-redirect.nl';
        $caddyRedirectOrderLineItem->product_uuid = $product->uuid;
        $caddyRedirectOrderLineItem->product_name = $product->name;
        $caddyRedirectOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $caddyRedirectOrderLineItem->gross_price = $price->price;
        $caddyRedirectOrderLineItem->net_price = $price->price;
        $caddyRedirectOrderLineItem->billing_period = $price->billing_period;
        $caddyRedirectOrderLineItem->contract_period = $price->contract_period;
        $caddyRedirectOrderLineItem->should_invoice = true;
        $caddyRedirectOrderLineItem->save();

        $caddyRedirectSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $caddyRedirectOrderLineItem
        );

        $caddyRedirectOrderLineItem->subscription_uuid = $caddyRedirectSubscription->uuid;
        $caddyRedirectOrderLineItem->save();

        $this->createInvoiceItem($caddyRedirectSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $caddyRedirectOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($caddyRedirectSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($caddyRedirectSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $caddyRedirectOrderLineItem->domain, 'destination' => 'invalid'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_REDIRECT;
        $provisionRequest->request_type = ProvisionType::REDIRECT;
        $provisionRequest->provision_provider = ProvisionProvider::INTERNAL;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'destination' => ['invalid_domain' => 'Destination is not a valid domain.'],
                    'type' => ['required' => 'Missing redirect type; 301, 302, or iFrame.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($caddyRedirectSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($caddyRedirectSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $caddyRedirectOrderLineItem->domain, 'destination' => 'google.nl', 'type' => '301'], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_REDIRECT;
        $retryProvisionRequest->request_type = ProvisionType::REDIRECT;
        $retryProvisionRequest->provision_provider = ProvisionProvider::INTERNAL;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'added redirect successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $caddyContext = new CaddyContext();
        $caddyContext->context_uuid = Uuid::fromString($domainSubscription->uuid);
        $caddyContext->host = 'yourhosting';
        $caddyContext->save();

        $redirectDeployment = new RedirectDeployment();
        $redirectDeployment->uuid = Uuid::uuid4();
        $redirectDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $redirectDeployment->source = $caddyRedirectOrderLineItem->domain;
        $redirectDeployment->destination = 'yourhosting.nl';
        $redirectDeployment->type = RedirectType::PERMANENT;
        $redirectDeployment->context_uuid = $caddyContext->context_uuid;
        $redirectDeployment->save();

        $caddyRedirectDeployment = new CaddyRedirectDeployment();
        $caddyRedirectDeployment->uuid = Uuid::uuid4();
        $caddyRedirectDeployment->redirect_deployment_id = $redirectDeployment->id;
        $caddyRedirectDeployment->caddy_id = 'yh12345';
        $caddyRedirectDeployment->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $caddyRedirectOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function singleDomain(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SSL_SINGLE_DOMAIN, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SSL_SINGLE_DOMAIN_REGISTRATION_PRICE, ProductPriceComponent::class);
        $sslProvider = $this->referenceRepo->get(ProductReference::SSL_PROVIDER_RTR, Provider::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sslSingleDomainOrderLineItem = new OrderLineItem();
        $sslSingleDomainOrderLineItem->order_id = $order->id;
        $sslSingleDomainOrderLineItem->domain = 'ssl-single-domain.nl';
        $sslSingleDomainOrderLineItem->product_uuid = $product->uuid;
        $sslSingleDomainOrderLineItem->product_name = $product->name;
        $sslSingleDomainOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $sslSingleDomainOrderLineItem->gross_price = $price->price;
        $sslSingleDomainOrderLineItem->net_price = $price->price;
        $sslSingleDomainOrderLineItem->billing_period = $price->billing_period;
        $sslSingleDomainOrderLineItem->contract_period = $price->contract_period;
        $sslSingleDomainOrderLineItem->should_invoice = true;
        $sslSingleDomainOrderLineItem->save();

        $sslSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $sslSingleDomainOrderLineItem
        );

        $sslSingleDomainOrderLineItem->subscription_uuid = $sslSubscription->uuid;
        $sslSingleDomainOrderLineItem->save();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $sslSubscription->uuid;
        $sslDeployment->certificate_id = 1;
        $sslDeployment->request_id = 1;
        $sslDeployment->provider_id = $sslProvider->id;
        $sslDeployment->custom_csr = false;
        $sslDeployment->has_reissued = false;
        $sslDeployment->webhook_request = '{}';
        $sslDeployment->webhook_request_received = CarbonImmutable::yesterday();
        $sslDeployment->last_result = '{}';
        $sslDeployment->last_result_received = CarbonImmutable::yesterday();
        $sslDeployment->expire_date = CarbonImmutable::now()->addMonths(6);
        $sslDeployment->save();

        $this->createInvoiceItem($sslSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $sslSingleDomainOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $sslSingleDomainOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function wildcard(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SSL_WILDCARD, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SSL_WILDCARD_REGISTRATION_PRICE, ProductPriceComponent::class);
        $sslProvider = $this->referenceRepo->get(ProductReference::SSL_PROVIDER_RTR, Provider::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sslSingleDomainOrderLineItem = new OrderLineItem();
        $sslSingleDomainOrderLineItem->order_id = $order->id;
        $sslSingleDomainOrderLineItem->domain = 'ssl-wildcard-domain.nl';
        $sslSingleDomainOrderLineItem->product_uuid = $product->uuid;
        $sslSingleDomainOrderLineItem->product_name = $product->name;
        $sslSingleDomainOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $sslSingleDomainOrderLineItem->gross_price = $price->price;
        $sslSingleDomainOrderLineItem->net_price = $price->price;
        $sslSingleDomainOrderLineItem->billing_period = $price->billing_period;
        $sslSingleDomainOrderLineItem->contract_period = $price->contract_period;
        $sslSingleDomainOrderLineItem->should_invoice = true;
        $sslSingleDomainOrderLineItem->save();

        $sslSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $sslSingleDomainOrderLineItem
        );

        $sslSingleDomainOrderLineItem->subscription_uuid = $sslSubscription->uuid;
        $sslSingleDomainOrderLineItem->save();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $sslSubscription->uuid;
        $sslDeployment->certificate_id = 1;
        $sslDeployment->request_id = 1;
        $sslDeployment->provider_id = $sslProvider->id;
        $sslDeployment->custom_csr = false;
        $sslDeployment->has_reissued = false;
        $sslDeployment->webhook_request = '{}';
        $sslDeployment->webhook_request_received = CarbonImmutable::yesterday();
        $sslDeployment->last_result = '{}';
        $sslDeployment->last_result_received = CarbonImmutable::yesterday();
        $sslDeployment->expire_date = CarbonImmutable::now()->addMonths(6);
        $sslDeployment->save();

        $this->createInvoiceItem($sslSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $sslSingleDomainOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $sslSingleDomainOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function extendedValidation(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SSL_EXTENDED_VALIDATION, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SSL_EXTENDED_VALIDATION_REGISTRATION_PRICE, ProductPriceComponent::class);
        $sslProvider = $this->referenceRepo->get(ProductReference::SSL_PROVIDER_RTR, Provider::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sslSingleDomainOrderLineItem = new OrderLineItem();
        $sslSingleDomainOrderLineItem->order_id = $order->id;
        $sslSingleDomainOrderLineItem->domain = 'ssl-extended-validation-domain.nl';
        $sslSingleDomainOrderLineItem->product_uuid = $product->uuid;
        $sslSingleDomainOrderLineItem->product_name = $product->name;
        $sslSingleDomainOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $sslSingleDomainOrderLineItem->gross_price = $price->price;
        $sslSingleDomainOrderLineItem->net_price = $price->price;
        $sslSingleDomainOrderLineItem->billing_period = $price->billing_period;
        $sslSingleDomainOrderLineItem->contract_period = $price->contract_period;
        $sslSingleDomainOrderLineItem->should_invoice = true;
        $sslSingleDomainOrderLineItem->save();

        $sslSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $sslSingleDomainOrderLineItem
        );

        $sslSingleDomainOrderLineItem->subscription_uuid = $sslSubscription->uuid;
        $sslSingleDomainOrderLineItem->save();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $sslSubscription->uuid;
        $sslDeployment->certificate_id = 1;
        $sslDeployment->request_id = 1;
        $sslDeployment->provider_id = $sslProvider->id;
        $sslDeployment->custom_csr = false;
        $sslDeployment->has_reissued = false;
        $sslDeployment->webhook_request = '{}';
        $sslDeployment->webhook_request_received = CarbonImmutable::yesterday();
        $sslDeployment->last_result = '{}';
        $sslDeployment->last_result_received = CarbonImmutable::yesterday();
        $sslDeployment->expire_date = CarbonImmutable::now()->addMonths(6);
        $sslDeployment->save();

        $this->createInvoiceItem($sslSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $sslSingleDomainOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $sslSingleDomainOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function sslPlaceholder(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SSL_PLACEHOLDER, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SSL_PLACEHOLDER_REGISTRATION_PRICE, ProductPriceComponent::class);
        $this->referenceRepo->get(ProductReference::SSL_PROVIDER_PLACEHOLDER, Provider::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sslPlaceHolderOrderLineItem = new OrderLineItem();
        $sslPlaceHolderOrderLineItem->order_id = $order->id;
        $sslPlaceHolderOrderLineItem->domain = 'ssl-placeholder-domain.nl';
        $sslPlaceHolderOrderLineItem->product_uuid = $product->uuid;
        $sslPlaceHolderOrderLineItem->product_name = $product->name;
        $sslPlaceHolderOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $sslPlaceHolderOrderLineItem->gross_price = $price->price;
        $sslPlaceHolderOrderLineItem->net_price = $price->price;
        $sslPlaceHolderOrderLineItem->billing_period = $price->billing_period;
        $sslPlaceHolderOrderLineItem->contract_period = $price->contract_period;
        $sslPlaceHolderOrderLineItem->should_invoice = true;
        $sslPlaceHolderOrderLineItem->save();

        $sslSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $sslPlaceHolderOrderLineItem
        );

        $sslPlaceHolderOrderLineItem->subscription_uuid = $sslSubscription->uuid;
        $sslPlaceHolderOrderLineItem->save();

        $this->createInvoiceItem($sslSubscription, $customer, $product);

        $domainOrderItem = $this->createDnsOrderItem(
            $order,
            $sslPlaceHolderOrderLineItem,
            $domainProduct,
            $domainPrice
        );

        $domainSubscription = $this->createSubscription($customer, $domainProduct, $domainPrice, $domainOrderItem);

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $this->createDomainDeployment($domainSubscription, $domainProvider, $domainContact);

        $this->createInvoiceItem($domainSubscription, $customer, $domainProduct);

        $dnsOrderItem = $this->createDnsOrderItem($order, $sslPlaceHolderOrderLineItem, $dnsProduct, $dnsPrice);

        $dnsSubscription = $this->createDnsSubscription(
            $domainSubscription,
            $customer,
            $dnsProduct,
            $dnsPrice,
            $dnsOrderItem
        );

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $this->createDnsDeploymentWithNameservers($dnsSubscription);

        $this->createInvoiceItem($dnsSubscription, $customer, $dnsProduct);
    }

    private function sslOnlyFromMigrations(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::SSL_SINGLE_DOMAIN, Product::class);
        $price = $this->referenceRepo->get(ProductReference::SSL_SINGLE_DOMAIN_REGISTRATION_PRICE, ProductPriceComponent::class);
        $sslProvider = $this->referenceRepo->get(ProductReference::SSL_PROVIDER_RTR, Provider::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $sslSingleDomainOrderLineItem = new OrderLineItem();
        $sslSingleDomainOrderLineItem->order_id = $order->id;
        $sslSingleDomainOrderLineItem->domain = 'ssl-only-from-migrations.nl';
        $sslSingleDomainOrderLineItem->product_uuid = $product->uuid;
        $sslSingleDomainOrderLineItem->product_name = $product->name;
        $sslSingleDomainOrderLineItem->status = OrderLineItemStatus::REGISTRATION;
        $sslSingleDomainOrderLineItem->gross_price = $price->price;
        $sslSingleDomainOrderLineItem->net_price = $price->price;
        $sslSingleDomainOrderLineItem->billing_period = $price->billing_period;
        $sslSingleDomainOrderLineItem->contract_period = $price->contract_period;
        $sslSingleDomainOrderLineItem->should_invoice = true;
        $sslSingleDomainOrderLineItem->save();

        $sslSubscription = $this->createSubscription(
            $customer,
            $product,
            $price,
            $sslSingleDomainOrderLineItem
        );

        $sslSingleDomainOrderLineItem->subscription_uuid = $sslSubscription->uuid;
        $sslSingleDomainOrderLineItem->save();

        $sslDeployment = new SslDeployment();
        $sslDeployment->subscription_uuid = $sslSubscription->uuid;
        $sslDeployment->certificate_id = 1;
        $sslDeployment->request_id = 1;
        $sslDeployment->provider_id = $sslProvider->id;
        $sslDeployment->custom_csr = false;
        $sslDeployment->has_reissued = false;
        $sslDeployment->webhook_request = '{}';
        $sslDeployment->webhook_request_received = CarbonImmutable::yesterday();
        $sslDeployment->last_result = '{}';
        $sslDeployment->last_result_received = CarbonImmutable::yesterday();
        $sslDeployment->expire_date = CarbonImmutable::now()->addMonths(6);
        $sslDeployment->save();

        $this->createInvoiceItem($sslSubscription, $customer, $product);
    }

    private function createDomainDeployment(
        Subscription $domainSubscription,
        Provider $domainProvider,
        DomainContact $domainContact
    ): void {
        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();
    }

    private function createDnsDeploymentWithNameservers(Subscription $dnsSubscription): void
    {
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);
    }

    private function createSubscription(
        Customer $customer,
        Product $product,
        ProductPriceComponent $productPrice,
        OrderLineItem $orderItem
    ): Subscription {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->uuid = Uuid::uuid4()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = $orderItem->domain;
        $subscription->contract_period = $productPrice->contract_period;
        $subscription->billing_period = $productPrice->billing_period;
        Assert::greaterThanEq($orderItem->net_price, 0);
        $subscription->net_price = $orderItem->net_price;
        Assert::greaterThanEq($orderItem->gross_price, 0);
        $subscription->gross_price = $orderItem->gross_price;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths(
            $subscription->billing_period
        );
        $subscription->end_date = $subscription->start_date->addMonths(
            $subscription->contract_period
        );
        $subscription->save();

        $subscriptionPrice = new SubscriptionPrice();
        $subscriptionPrice->subscription_id = $subscription->id;
        $subscriptionPrice->valid_from = $subscription->start_date;
        $subscriptionPrice->net_price = $subscription->net_price;
        $subscriptionPrice->save();

        $subscriptionPriceComponent = new SubscriptionPriceComponent();
        $subscriptionPriceComponent->subscription_price_id = $subscriptionPrice->id;
        $subscriptionPriceComponent->type = $productPrice->type;
        $subscriptionPriceComponent->percentage_discount = null;
        $subscriptionPriceComponent->fixed_discount = null;
        $subscriptionPriceComponent->fixed_price = $subscription->net_price;
        $subscriptionPriceComponent->new_price = $subscription->net_price;
        $subscriptionPriceComponent->order_applied = 1;
        $subscriptionPriceComponent->save();

        $subscription->subscription_price_id = $subscriptionPrice->id;
        $subscription->save();

        return $subscription;
    }

    private function createInvoiceItem(
        Subscription $subscription,
        Customer $customer,
        Product $product
    ): Invoice {
        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $subscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $subscription->start_date;
        $invoiceItem->end_date = $subscription->next_billing_date;
        $invoiceItem->period = $subscription->contract_period;
        $invoiceItem->net_price = $subscription->net_price;
        $invoiceItem->gross_price = $subscription->gross_price;
        $invoiceItem->title = $subscription->domain ?? $subscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $subscription->product->name, $subscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
        return $invoiceItem;
    }

    private function createDnsSubscription(
        Subscription $domainSubscription,
        Customer $customer,
        Product $dnsProduct,
        ProductPriceComponent $dnsPrice,
        OrderLineItem $dnsOrderItem
    ): Subscription {
        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        Assert::greaterThanEq($dnsOrderItem->net_price, 0);
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        Assert::greaterThanEq($dnsOrderItem->gross_price, 0);
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $mutation = new SubscriptionMutation();
        $mutation->subscription_id = $dnsSubscription->id;
        $mutation->product_id = $dnsProduct->id;
        $mutation->gross_price = 100;
        $mutation->net_price = 100;
        $mutation->billing_period = $dnsPrice->billing_period;
        $mutation->contract_period = $dnsPrice->contract_period;
        $mutation->mutated_at = CarbonImmutable::now();
        $mutation->save();

        return $dnsSubscription;
    }

    private function createDnsOrderItem(
        Order $order,
        OrderLineItem $orderlineItem,
        Product $dnsProduct,
        ProductPriceComponent $dnsPrice
    ): OrderLineItem {
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $orderlineItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();
        return $dnsOrderItem;
    }

    private function createDirectAdminHostingDeployment(
        Subscription $hostingWebOnlyPlusSubscription,
        Server $server,
        Provider|null $provider,
        string $username,
        ?Provider $mailOnlyProvider = null,
    ): void {
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingWebOnlyPlusSubscription->uuid;
        $hostingDeployment->provider_id = $provider?->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->sitebuilder_provider_id = null;
        $hostingDeployment->mail_only_provider_id = $mailOnlyProvider?->id;
        $hostingDeployment->basekit_user_ref = null;
        $hostingDeployment->basekit_site_ref = null;
        $hostingDeployment->basekit_server_id = null;
        $hostingDeployment->directadmin_customer_username = $username;
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingWebOnlyPlusSubscription->start_date->addHour();
        $hostingDeployment->save();
    }

    private function createDirectAdminMailOnlyHostingDeployment(
        Subscription $subscription,
        Server $mailOnlyServer,
        string $directadminCustomerUsername,
    ): void {
        $mailOnlyProvider = $this->referenceRepo->get(ProductReference::HOSTING_MAIL_ONLY_PROVIDER_DIRECT_ADMIN, Provider::class);

        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription()->associate($subscription);
        $hostingDeployment->mailProvider()->associate($mailOnlyProvider);
        $hostingDeployment->mailOnlyServer()->associate($mailOnlyServer);
        $hostingDeployment->directadmin_customer_username = $directadminCustomerUsername;
        $hostingDeployment->save();
    }

    private function createPleskHostingDeployment(
        Subscription $subscription,
        Server $server,
        Provider|null $provider,
        string $username,
        Provider|null $mailOnlyProvider = null,
    ): void {
        $hostingDeployment = new HostingDeployment();
        $hostingDeployment->subscription_uuid = $subscription->uuid;
        $hostingDeployment->provider_id = $provider?->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->mail_only_provider_id = $mailOnlyProvider?->id;
        $hostingDeployment->plesk_customer_username = $username;
        $hostingDeployment->plesk_customer_id = 1337;
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $subscription->start_date->addHour();
        $hostingDeployment->save();
    }

    private function emailHistory(Customer $customer): void
    {
        $emailHistory = new EmailHistory();
        $emailHistory->template_id = $this->referenceRepo->get(PlatformReference::MAIL_TEMPLATE_ACTIVATE_ACCOUNT, Template::class)->id;
        $emailHistory->uuid = Uuid::uuid4()->toString();
        $emailHistory->receiver_email = $customer->getEmail();
        $emailHistory->receiver_type = ReceiverType::CUSTOMER;
        $emailHistory->receiver_uuid = $customer->uuid->toString();
        $emailHistory->payload = '{"activationCode":"abcdef1234", "activationUrl":"https://lighthouse.sandwaveio.dev/activate/abcdef1234"}';
        $emailHistory->hubspot_id = '4147483647123';
        $emailHistory->hubspot_status = 'COMPLETE';
        $emailHistory->sent_at = CarbonImmutable::now();
        $emailHistory->save();

        $emailHistory = new EmailHistory();
        $emailHistory->template_id = $this->referenceRepo->get(PlatformReference::MAIL_TEMPLATE_PLESK_DETAILS, Template::class)->id;
        $emailHistory->uuid = Uuid::uuid4()->toString();
        $emailHistory->receiver_email = $customer->getEmail();
        $emailHistory->receiver_type = ReceiverType::CUSTOMER;
        $emailHistory->receiver_uuid = $customer->uuid->toString();
        $emailHistory->payload = '{"username":"plesk-username", "domain":"plesk-domain", "ipv4_address":"plesk-ipv4_address", "password":"Rb0zUnGwduPT2o4y75465@$"}';
        $emailHistory->hubspot_id = '4147483647124';
        $emailHistory->hubspot_status = 'COMPLETE';
        $emailHistory->sent_at = CarbonImmutable::now()->subMonths(1);
        $emailHistory->save();

        $emailHistory = new EmailHistory();
        $emailHistory->template_id = $this->referenceRepo->get(PlatformReference::MAIL_TEMPLATE_ACTIVATE_ACCOUNT, Template::class)->id;
        $emailHistory->uuid = Uuid::uuid4()->toString();
        $emailHistory->receiver_email = $customer->getEmail();
        $emailHistory->receiver_type = ReceiverType::CUSTOMER;
        $emailHistory->receiver_uuid = $customer->uuid->toString();
        $emailHistory->payload = '{"recoveryCode": "abcdef1234", "recoveryLink": "https://lighthouse.sandwaveio.dev/recover/abcdef1234"}';
        $emailHistory->hubspot_id = '4147483647125';
        $emailHistory->hubspot_status = 'PROCESSING';
        $emailHistory->sent_at = CarbonImmutable::now()->subDays(1);
        $emailHistory->save();
    }

    private function appsForBusiness(Customer $customer): void
    {
        $customerInfo = new Microsoft365CustomerInfo();
        $customerInfo->customer_id = $customer->id;
        $customerInfo->kpn_customer_id = 'CID12345';
        $customerInfo->tenant_order_id = 10000001;
        $customerInfo->technical_status = Microsoft365ProcessStatus::ACTIVE;
        $customerInfo->tenant_name = $customer->customer_number . '.onmicrosoft.com';
        $customerInfo->primary_domain = 'hostingwebonlybasic.nl';
        $customerInfo->primary_domain_status = PrimaryDomainStatus::ACTIVE;
        $customerInfo->tenant_id = Uuid::uuid4()->toString();
        $customerInfo->tenant_access_verified = true;
        $customerInfo->type = CustomerInfoType::TRANSFER;
        $customerInfo->mca_signed_at = CarbonImmutable::now();
        $customerInfo->save();

        $parentProduct = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT, Product::class);
        $childProduct = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD, Product::class);
        $parentProductPriceMonth = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT_REGISTRATION_PRICE_MONTH, ProductPriceComponent::class);
        $childProductPriceMonth = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD_REGISTRATION_PRICE_MONTH, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $parentProductPriceMonth->price + $childProductPriceMonth->price + $childProductPriceMonth->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $parentSubscriptionOrderItem = new OrderLineItem();
        $parentSubscriptionOrderItem->order_id = $order->id;
        $parentSubscriptionOrderItem->domain = null;
        $parentSubscriptionOrderItem->product_uuid = $parentProduct->uuid;
        $parentSubscriptionOrderItem->product_name = $parentProduct->name;
        $parentSubscriptionOrderItem->status = OrderLineItemStatus::from($parentProductPriceMonth->type->value);
        $parentSubscriptionOrderItem->gross_price = $parentProductPriceMonth->price;
        $parentSubscriptionOrderItem->net_price = $parentProductPriceMonth->price;
        $parentSubscriptionOrderItem->billing_period = $parentProductPriceMonth->billing_period;
        $parentSubscriptionOrderItem->contract_period = $parentProductPriceMonth->contract_period;
        $parentSubscriptionOrderItem->should_invoice = true;
        $parentSubscriptionOrderItem->save();

        $parentSubscriptionMonth = new Subscription();
        $parentSubscriptionMonth->uuid = Uuid::uuid4()->toString();
        $parentSubscriptionMonth->customer_id = $customer->id;
        $parentSubscriptionMonth->product_uuid = $parentProduct->uuid;
        $parentSubscriptionMonth->domain = $parentSubscriptionOrderItem->domain;
        $parentSubscriptionMonth->contract_period = $parentProductPriceMonth->contract_period;
        $parentSubscriptionMonth->billing_period = $parentProductPriceMonth->billing_period;
        $parentSubscriptionMonth->net_price = $parentSubscriptionOrderItem->net_price;
        $parentSubscriptionMonth->gross_price = $parentSubscriptionOrderItem->gross_price;
        $parentSubscriptionMonth->technical_status = TechnicalStatus::OK->value;
        $parentSubscriptionMonth->start_date = CarbonImmutable::yesterday();
        $parentSubscriptionMonth->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $parentSubscriptionMonth->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $parentSubscriptionMonth->save();

        $parentSubscriptionMonthPrice = new SubscriptionPrice();
        $parentSubscriptionMonthPrice->subscription_id = $parentSubscriptionMonth->id;
        $parentSubscriptionMonthPrice->valid_from = $parentSubscriptionMonth->start_date;
        $parentSubscriptionMonthPrice->net_price = $parentSubscriptionMonth->net_price;
        $parentSubscriptionMonthPrice->save();

        $parentSubscriptionMonthPriceComponent = new SubscriptionPriceComponent();
        $parentSubscriptionMonthPriceComponent->subscription_price_id = $parentSubscriptionMonthPrice->id;
        $parentSubscriptionMonthPriceComponent->type = $parentProductPriceMonth->type;
        $parentSubscriptionMonthPriceComponent->percentage_discount = null;
        $parentSubscriptionMonthPriceComponent->fixed_discount = null;
        $parentSubscriptionMonthPriceComponent->fixed_price = $parentSubscriptionMonth->net_price;
        $parentSubscriptionMonthPriceComponent->new_price = $parentSubscriptionMonth->net_price;
        $parentSubscriptionMonthPriceComponent->order_applied = 1;
        $parentSubscriptionMonthPriceComponent->save();

        $parentSubscriptionMonth->subscription_price_id = $parentSubscriptionMonthPrice->id;
        $parentSubscriptionMonth->save();

        $parentSubscriptionOrderItem->subscription_uuid = $parentSubscriptionMonth->uuid;
        $parentSubscriptionOrderItem->save();

        $childSubscriptionMonth = new Subscription();
        $childSubscriptionMonth->uuid = Uuid::uuid4()->toString();
        $childSubscriptionMonth->customer_id = $customer->id;
        $childSubscriptionMonth->product_uuid = $childProduct->uuid;
        $childSubscriptionMonth->domain = $parentSubscriptionOrderItem->domain;
        $childSubscriptionMonth->contract_period = $childProductPriceMonth->contract_period;
        $childSubscriptionMonth->billing_period = $childProductPriceMonth->billing_period;
        $childSubscriptionMonth->net_price = $childProductPriceMonth->price;
        $childSubscriptionMonth->gross_price = $childProductPriceMonth->price;
        $childSubscriptionMonth->technical_status = TechnicalStatus::OK->value;
        $childSubscriptionMonth->start_date = CarbonImmutable::yesterday();
        $childSubscriptionMonth->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $childSubscriptionMonth->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $childSubscriptionMonth->parent_subscription_id = $parentSubscriptionMonth->id;
        $childSubscriptionMonth->save();

        $childSubscriptionMonthPrice = new SubscriptionPrice();
        $childSubscriptionMonthPrice->subscription_id = $childSubscriptionMonth->id;
        $childSubscriptionMonthPrice->valid_from = $childSubscriptionMonth->start_date;
        $childSubscriptionMonthPrice->net_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPrice->save();

        $childSubscriptionMonthPriceComponent = new SubscriptionPriceComponent();
        $childSubscriptionMonthPriceComponent->subscription_price_id = $childSubscriptionMonthPrice->id;
        $childSubscriptionMonthPriceComponent->type = $childProductPriceMonth->type;
        $childSubscriptionMonthPriceComponent->percentage_discount = null;
        $childSubscriptionMonthPriceComponent->fixed_discount = null;
        $childSubscriptionMonthPriceComponent->fixed_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPriceComponent->new_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPriceComponent->order_applied = 1;
        $childSubscriptionMonthPriceComponent->save();

        $childSubscriptionMonth->subscription_price_id = $childSubscriptionMonthPrice->id;
        $childSubscriptionMonth->save();

        $childSubscriptionMonth = new Subscription();
        $childSubscriptionMonth->uuid = Uuid::uuid4()->toString();
        $childSubscriptionMonth->customer_id = $customer->id;
        $childSubscriptionMonth->product_uuid = $childProduct->uuid;
        $childSubscriptionMonth->domain = $parentSubscriptionOrderItem->domain;
        $childSubscriptionMonth->contract_period = $childProductPriceMonth->contract_period;
        $childSubscriptionMonth->billing_period = $childProductPriceMonth->billing_period;
        $childSubscriptionMonth->net_price = $childProductPriceMonth->price;
        $childSubscriptionMonth->gross_price = $childProductPriceMonth->price;
        $childSubscriptionMonth->technical_status = TechnicalStatus::OK->value;
        $childSubscriptionMonth->start_date = CarbonImmutable::yesterday();
        $childSubscriptionMonth->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $childSubscriptionMonth->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $childSubscriptionMonth->parent_subscription_id = $parentSubscriptionMonth->id;
        $childSubscriptionMonth->save();

        $childSubscriptionMonthPrice = new SubscriptionPrice();
        $childSubscriptionMonthPrice->subscription_id = $childSubscriptionMonth->id;
        $childSubscriptionMonthPrice->valid_from = $childSubscriptionMonth->start_date;
        $childSubscriptionMonthPrice->net_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPrice->save();

        $childSubscriptionMonthPriceComponent = new SubscriptionPriceComponent();
        $childSubscriptionMonthPriceComponent->subscription_price_id = $childSubscriptionMonthPrice->id;
        $childSubscriptionMonthPriceComponent->type = $childProductPriceMonth->type;
        $childSubscriptionMonthPriceComponent->percentage_discount = null;
        $childSubscriptionMonthPriceComponent->fixed_discount = null;
        $childSubscriptionMonthPriceComponent->fixed_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPriceComponent->new_price = $childSubscriptionMonth->net_price;
        $childSubscriptionMonthPriceComponent->order_applied = 1;
        $childSubscriptionMonthPriceComponent->save();

        $childSubscriptionMonth->subscription_price_id = $childSubscriptionMonthPrice->id;
        $childSubscriptionMonth->save();

        $deployment = new Microsoft365Deployment();
        $deployment->subscription_id = $parentSubscriptionMonth->id;
        $deployment->kpn_status = Microsoft365OrderStatus::ACTIVE;
        $deployment->kpn_order_id = 12345;
        $deployment->microsoft365_customer_info_id = $customerInfo->id;
        $deployment->save();

        $parentProduct = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT, Product::class);
        $childProduct = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD, Product::class);
        $parentProductPriceYear = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_PARENT_REGISTRATION_PRICE_YEAR, ProductPriceComponent::class);
        $childProductPriceYear = $this->referenceRepo->get(ProductReference::MICROSOFT_APPS_FOR_BUSINESS_CHILD_REGISTRATION_PRICE_YEAR, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $parentProductPriceYear->price + $childProductPriceYear->price + $childProductPriceYear->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->save();

        $parentSubscriptionOrderItem = new OrderLineItem();
        $parentSubscriptionOrderItem->order_id = $order->id;
        $parentSubscriptionOrderItem->domain = null;
        $parentSubscriptionOrderItem->product_uuid = $parentProduct->uuid;
        $parentSubscriptionOrderItem->product_name = $parentProduct->name;
        $parentSubscriptionOrderItem->status = OrderLineItemStatus::from($parentProductPriceYear->type->value);
        $parentSubscriptionOrderItem->gross_price = $parentProductPriceYear->price;
        $parentSubscriptionOrderItem->net_price = $parentProductPriceYear->price;
        $parentSubscriptionOrderItem->billing_period = $parentProductPriceYear->billing_period;
        $parentSubscriptionOrderItem->contract_period = $parentProductPriceYear->contract_period;
        $parentSubscriptionOrderItem->should_invoice = true;
        $parentSubscriptionOrderItem->save();

        $parentSubscriptionYear = new Subscription();
        $parentSubscriptionYear->uuid = Uuid::uuid4()->toString();
        $parentSubscriptionYear->customer_id = $customer->id;
        $parentSubscriptionYear->product_uuid = $parentProduct->uuid;
        $parentSubscriptionYear->domain = $parentSubscriptionOrderItem->domain;
        $parentSubscriptionYear->contract_period = $parentProductPriceYear->contract_period;
        $parentSubscriptionYear->billing_period = $parentProductPriceYear->billing_period;
        $parentSubscriptionYear->net_price = $parentSubscriptionOrderItem->net_price;
        $parentSubscriptionYear->gross_price = $parentSubscriptionOrderItem->gross_price;
        $parentSubscriptionYear->technical_status = TechnicalStatus::OK->value;
        $parentSubscriptionYear->start_date = CarbonImmutable::yesterday();
        $parentSubscriptionYear->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $parentSubscriptionYear->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $parentSubscriptionYear->save();

        $parentSubscriptionYearPrice = new SubscriptionPrice();
        $parentSubscriptionYearPrice->subscription_id = $parentSubscriptionYear->id;
        $parentSubscriptionYearPrice->valid_from = $parentSubscriptionYear->start_date;
        $parentSubscriptionYearPrice->net_price = $parentSubscriptionYear->net_price;
        $parentSubscriptionYearPrice->save();

        $parentSubscriptionYearPriceComponent = new SubscriptionPriceComponent();
        $parentSubscriptionYearPriceComponent->subscription_price_id = $parentSubscriptionYearPrice->id;
        $parentSubscriptionYearPriceComponent->type = $parentProductPriceYear->type;
        $parentSubscriptionYearPriceComponent->percentage_discount = null;
        $parentSubscriptionYearPriceComponent->fixed_discount = null;
        $parentSubscriptionYearPriceComponent->fixed_price = $parentSubscriptionYear->net_price;
        $parentSubscriptionYearPriceComponent->new_price = $parentSubscriptionYear->net_price;
        $parentSubscriptionYearPriceComponent->order_applied = 1;
        $parentSubscriptionYearPriceComponent->save();

        $parentSubscriptionYear->subscription_price_id = $parentSubscriptionYearPrice->id;
        $parentSubscriptionYear->save();

        $parentSubscriptionOrderItem->subscription_uuid = $parentSubscriptionMonth->uuid;
        $parentSubscriptionOrderItem->save();

        $childSubscriptionYear = new Subscription();
        $childSubscriptionYear->uuid = Uuid::uuid4()->toString();
        $childSubscriptionYear->customer_id = $customer->id;
        $childSubscriptionYear->product_uuid = $childProduct->uuid;
        $childSubscriptionYear->domain = $parentSubscriptionOrderItem->domain;
        $childSubscriptionYear->contract_period = $childProductPriceYear->contract_period;
        $childSubscriptionYear->billing_period = $childProductPriceYear->billing_period;
        $childSubscriptionYear->net_price = $childProductPriceYear->price;
        $childSubscriptionYear->gross_price = $childProductPriceYear->price;
        $childSubscriptionYear->technical_status = TechnicalStatus::OK->value;
        $childSubscriptionYear->start_date = CarbonImmutable::yesterday();
        $childSubscriptionYear->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $childSubscriptionYear->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $childSubscriptionYear->parent_subscription_id = $parentSubscriptionMonth->id;
        $childSubscriptionYear->save();

        $childSubscriptionYearPrice = new SubscriptionPrice();
        $childSubscriptionYearPrice->subscription_id = $childSubscriptionYear->id;
        $childSubscriptionYearPrice->valid_from = $childSubscriptionYear->start_date;
        $childSubscriptionYearPrice->net_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPrice->save();

        $childSubscriptionYearPriceComponent = new SubscriptionPriceComponent();
        $childSubscriptionYearPriceComponent->subscription_price_id = $childSubscriptionYearPrice->id;
        $childSubscriptionYearPriceComponent->type = $childProductPriceYear->type;
        $childSubscriptionYearPriceComponent->percentage_discount = null;
        $childSubscriptionYearPriceComponent->fixed_discount = null;
        $childSubscriptionYearPriceComponent->fixed_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPriceComponent->new_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPriceComponent->order_applied = 1;
        $childSubscriptionYearPriceComponent->save();

        $childSubscriptionYear->subscription_price_id = $childSubscriptionYearPrice->id;
        $childSubscriptionYear->save();

        $childSubscriptionYear = new Subscription();
        $childSubscriptionYear->uuid = Uuid::uuid4()->toString();
        $childSubscriptionYear->customer_id = $customer->id;
        $childSubscriptionYear->product_uuid = $childProduct->uuid;
        $childSubscriptionYear->domain = $parentSubscriptionOrderItem->domain;
        $childSubscriptionYear->contract_period = $childProductPriceYear->contract_period;
        $childSubscriptionYear->billing_period = $childProductPriceYear->billing_period;
        $childSubscriptionYear->net_price = $childProductPriceYear->price;
        $childSubscriptionYear->gross_price = $childProductPriceYear->price;
        $childSubscriptionYear->technical_status = TechnicalStatus::OK->value;
        $childSubscriptionYear->start_date = CarbonImmutable::yesterday();
        $childSubscriptionYear->next_billing_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->billing_period);
        $childSubscriptionYear->end_date = $parentSubscriptionMonth->start_date->addMonths($parentSubscriptionMonth->contract_period);
        $childSubscriptionYear->parent_subscription_id = $parentSubscriptionMonth->id;
        $childSubscriptionYear->save();

        $childSubscriptionYearPrice = new SubscriptionPrice();
        $childSubscriptionYearPrice->subscription_id = $childSubscriptionYear->id;
        $childSubscriptionYearPrice->valid_from = $childSubscriptionYear->start_date;
        $childSubscriptionYearPrice->net_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPrice->save();

        $childSubscriptionYearPriceComponent = new SubscriptionPriceComponent();
        $childSubscriptionYearPriceComponent->subscription_price_id = $childSubscriptionYearPrice->id;
        $childSubscriptionYearPriceComponent->type = $childProductPriceYear->type;
        $childSubscriptionYearPriceComponent->percentage_discount = null;
        $childSubscriptionYearPriceComponent->fixed_discount = null;
        $childSubscriptionYearPriceComponent->fixed_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPriceComponent->new_price = $childSubscriptionYear->net_price;
        $childSubscriptionYearPriceComponent->order_applied = 1;
        $childSubscriptionYearPriceComponent->save();

        $childSubscriptionYear->subscription_price_id = $childSubscriptionYearPrice->id;
        $childSubscriptionYear->save();

        $deployment = new Microsoft365Deployment();
        $deployment->subscription_id = $parentSubscriptionYear->id;
        $deployment->kpn_status = Microsoft365OrderStatus::ACTIVE;
        $deployment->kpn_order_id = 23456;
        $deployment->microsoft365_customer_info_id = $customerInfo->id;
        $deployment->save();

        $microsoft365CustomerXmlResponse = (string) file_get_contents(__DIR__ . '/Data/NewCustomerResponse.xml');
        $microsoft365SubscriptionXmlResponse = (string) file_get_contents(__DIR__ . '/Data/NewCloudLicenseOrderResponse.xml');
        $microsoft365TerminationXmlResponse = (string) file_get_contents(__DIR__ . '/Data/TerminateOrderResponse.xml');

        $httpLog = new Microsoft365HttpLog();
        $httpLog->kpn_customer_id = $customerInfo->kpn_customer_id;
        $httpLog->kpn_order_id = null;
        $httpLog->tenant_name = $customerInfo->tenant_name;
        $httpLog->partner_reference = $customer->id . '/' . $customerInfo->id;
        $httpLog->log = $microsoft365CustomerXmlResponse;
        $httpLog->xml_root_name = 'NewCustomerResponse_V3';
        $httpLog->save();

        $httpLog = new Microsoft365HttpLog();
        $httpLog->kpn_customer_id = null;
        $httpLog->kpn_order_id = null;
        $httpLog->tenant_name = $customerInfo->tenant_name;
        $httpLog->partner_reference = null;
        $httpLog->log = $microsoft365SubscriptionXmlResponse;
        $httpLog->xml_root_name = 'NewCloudLicenseOrderResponse_V4';
        $httpLog->subscription_id = $parentSubscriptionMonth->id;
        $httpLog->save();

        $httpLog = new Microsoft365HttpLog();
        $httpLog->kpn_customer_id = null;
        $httpLog->kpn_order_id = null;
        $httpLog->tenant_name = null;
        $httpLog->partner_reference = null;
        $httpLog->log = $microsoft365TerminationXmlResponse;
        $httpLog->xml_root_name = 'TerminateOrderResponse_V1';
        $httpLog->subscription_id = $parentSubscriptionYear->id;
        $httpLog->save();

        $httpLog = new Microsoft365HttpLog();
        $httpLog->kpn_customer_id = null;
        $httpLog->kpn_order_id = (string) $deployment->kpn_order_id;
        $httpLog->tenant_name = $customerInfo->tenant_name;
        $httpLog->partner_reference = null;
        $httpLog->log = str_replace('<OrderId>1</OrderId>', '<OrderId>2</OrderId>', $microsoft365SubscriptionXmlResponse);
        $httpLog->xml_root_name = 'NewCloudLicenseOrderResponse_V4';
        $httpLog->subscription_id = $parentSubscriptionYear->id;
        $httpLog->save();

        $syncLog = new Microsoft365SyncLog();
        $syncLog->log = 'Watching 2 Microsoft365 customers.';
        $syncLog->save();

        $syncLog = new Microsoft365SyncLog();
        $syncLog->log = sprintf('Microsoft365 subscription was kpn_order_id {0} and is now updated to {%d}. Updating subscription with the same product.', $deployment->kpn_order_id);
        $syncLog->microsoft365_customer_info_id = $customerInfo->id;
        $syncLog->microsoft365_deployment_id = $deployment->id;
        $syncLog->save();

        $syncLog = new Microsoft365SyncLog();
        $syncLog->log = 'Finished watching the Microsoft365 subscriptions.';
        $syncLog->save();
    }

    private function resellerBrons(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_BRONS, Product::class);

        $price = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_BRONS_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'reseller-hosting-brons.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new ResellerHostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->disk_space = 1000;
        $hostingDeployment->max_users = 5;
        $hostingDeployment->max_domains = 5;
        $hostingDeployment->max_email_addresses = 5;
        $hostingDeployment->max_traffic = 1000;
        $hostingDeployment->max_databases = 5;
        $hostingDeployment->directadmin_customer_username = 'da-reseller-hosting-brons-username';
        $hostingDeployment->last_created_result = null;
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function resellerSilver(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_SILVER, Product::class);

        $price = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_SILVER_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'reseller-hosting-silver.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new ResellerHostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->disk_space = 5000;
        $hostingDeployment->max_users = 20;
        $hostingDeployment->max_domains = 20;
        $hostingDeployment->max_email_addresses = 20;
        $hostingDeployment->max_traffic = 5000;
        $hostingDeployment->max_databases = 20;
        $hostingDeployment->directadmin_customer_username = 'da-reseller-hosting-silver-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function resellerGold(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_GOLD, Product::class);

        $price = $this->referenceRepo->get(ProductReference::RESELLER_HOSTING_GOLD_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::HOSTING_PROVIDER_DIRECT_ADMIN, Provider::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);

        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'reseller-hosting-gold.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $hostingDeployment = new ResellerHostingDeployment();
        $hostingDeployment->subscription_uuid = $hostingSubscription->uuid;
        $hostingDeployment->provider_id = $provider->id;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->disk_space = 20000;
        $hostingDeployment->max_users = 100;
        $hostingDeployment->max_domains = 100;
        $hostingDeployment->max_email_addresses = 100;
        $hostingDeployment->max_traffic = 20000;
        $hostingDeployment->max_databases = 100;
        $hostingDeployment->directadmin_customer_username = 'da-reseller-hosting-gold-username';
        $hostingDeployment->last_created_result = '{}';
        $hostingDeployment->last_created_result_received = $hostingSubscription->start_date->addHour();
        $hostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function paidRedirect(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::REDIRECT_PAID_REDIRECT, Product::class);
        $price = $this->referenceRepo->get(ProductReference::REDIRECT_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $orderItem = new OrderLineItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_uuid = $product->uuid;
        $orderItem->product_name = $product->name;
        $orderItem->status = OrderLineItemStatus::REGISTRATION;
        $orderItem->gross_price = $price->price;
        $orderItem->net_price = $price->price;
        $orderItem->billing_period = $price->billing_period;
        $orderItem->contract_period = $price->contract_period;
        $orderItem->should_invoice = true;
        $orderItem->save();

        $subscription = new Subscription();
        $subscription->uuid = Uuid::uuid4()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->contract_period = 1;
        $subscription->billing_period = 1;
        $subscription->net_price = $price->price;
        $subscription->gross_price = 650000;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = CarbonImmutable::yesterday();
        $subscription->end_date = CarbonImmutable::yesterday();
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

        $orderItem->subscription_uuid = $subscription->uuid;
        $orderItem->save();

        $invoice = new Invoice();
        $invoice->subscription_id = $subscription->id;
        $invoice->customer_id = $customer->id;
        $invoice->product_id = $product->id;
        $invoice->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoice->vat_rate = $customer->vat_rate ?? 0;
        $invoice->ledger_code = $product->productGroup->ledger_code;
        $invoice->paid = false;
        $invoice->start_date = $subscription->start_date;
        $invoice->end_date = $subscription->next_billing_date;
        $invoice->period = $subscription->contract_period;
        $invoice->gross_price = $subscription->gross_price;
        $invoice->net_price = $subscription->net_price;
        $invoice->title = $subscription->domain ?? $subscription->product->name;
        $invoice->description = sprintf('%s for %s', $subscription->product->name, $subscription->domain);
        $invoice->group_label = null;
        $invoice->type = InvoiceLine::TYPE_DEFAULT;
        $invoice->prepaid_reference = null;
        $invoice->save();
    }

    private function domainNlWithArgewebBusinessUnitRtr(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_RTR, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);
        $businessUnit = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_ARGEWEB, DomainProviderBusinessUnit::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-argeweb-rtr.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $hubspotCustomerEventSuccess = new HubspotEvent();
        $hubspotCustomerEventSuccess->customer_id = $customer->id;
        $hubspotCustomerEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotCustomerEventSuccess->message = 'Created new Hubspot contact';
        $hubspotCustomerEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotCustomerEventSuccess->save();

        $hubspotSubscriptionEventSuccess = new HubspotEvent();
        $hubspotSubscriptionEventSuccess->customer_id = $customer->id;
        $hubspotSubscriptionEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotSubscriptionEventSuccess->message = 'Created new Hubspot subscription';
        $hubspotSubscriptionEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotSubscriptionEventSuccess->save();

        $hubspotOneTimeServiceEventSuccess = new HubspotEvent();
        $hubspotOneTimeServiceEventSuccess->customer_id = $customer->id;
        $hubspotOneTimeServiceEventSuccess->event = 'Synchronizing one time service to Hubspot';
        $hubspotOneTimeServiceEventSuccess->message = 'Created new Hubspot one time service';
        $hubspotOneTimeServiceEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotOneTimeServiceEventSuccess->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->domain_business_unit_id = $businessUnit->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function domainNlWithWaterfrontBusinessUnitRtr(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_RTR, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);
        $businessUnit = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_BUSINESS_UNIT_WATERFRONT, DomainProviderBusinessUnit::class);

        // DNS dependecies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-with-waterfront-rtr.nl';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $hubspotCustomerEventSuccess = new HubspotEvent();
        $hubspotCustomerEventSuccess->customer_id = $customer->id;
        $hubspotCustomerEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotCustomerEventSuccess->message = 'Created new Hubspot contact';
        $hubspotCustomerEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotCustomerEventSuccess->save();

        $hubspotSubscriptionEventSuccess = new HubspotEvent();
        $hubspotSubscriptionEventSuccess->customer_id = $customer->id;
        $hubspotSubscriptionEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotSubscriptionEventSuccess->message = 'Created new Hubspot subscription';
        $hubspotSubscriptionEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotSubscriptionEventSuccess->save();

        $hubspotOneTimeServiceEventSuccess = new HubspotEvent();
        $hubspotOneTimeServiceEventSuccess->customer_id = $customer->id;
        $hubspotOneTimeServiceEventSuccess->event = 'Synchronizing one time service to Hubspot';
        $hubspotOneTimeServiceEventSuccess->message = 'Created new Hubspot one time service';
        $hubspotOneTimeServiceEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotOneTimeServiceEventSuccess->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->domain_business_unit_id = $businessUnit->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();
    }

    private function domainFrWithTrusteeAddon(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_FR, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_FR_REGISTRATION_PRICE, ProductPriceComponent::class);
        $provider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        // DNS dependencies
        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = 'domain-avec-trustee-addon.fr';
        $domainOrderItem->product_uuid = $product->uuid;
        $domainOrderItem->product_name = $product->name;
        $domainOrderItem->status = OrderLineItemStatus::from($price->type->value);
        $domainOrderItem->gross_price = $price->price;
        $domainOrderItem->net_price = $price->price;
        $domainOrderItem->billing_period = $price->billing_period;
        $domainOrderItem->contract_period = $price->contract_period;
        $domainOrderItem->processed_at = CarbonImmutable::now();
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $product->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $price->contract_period;
        $domainSubscription->billing_period = $price->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $price->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $hubspotSubscriptionEventSuccess = new HubspotEvent();
        $hubspotSubscriptionEventSuccess->customer_id = $customer->id;
        $hubspotSubscriptionEventSuccess->event = 'Synchronizing subscription to Hubspot';
        $hubspotSubscriptionEventSuccess->message = 'Created new Hubspot subscription';
        $hubspotSubscriptionEventSuccess->status = HubspotEventStatus::SUCCESS;
        $hubspotSubscriptionEventSuccess->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $provider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $domainInvoiceItem = new Invoice();
        $domainInvoiceItem->subscription_id = $domainSubscription->id;
        $domainInvoiceItem->customer_id = $customer->id;
        $domainInvoiceItem->product_id = $product->id;
        $domainInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $domainInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $domainInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $domainInvoiceItem->paid = false;
        $domainInvoiceItem->start_date = $domainSubscription->start_date;
        $domainInvoiceItem->end_date = $domainSubscription->next_billing_date;
        $domainInvoiceItem->period = $domainSubscription->contract_period;
        $domainInvoiceItem->gross_price = $domainSubscription->gross_price;
        $domainInvoiceItem->net_price = $domainSubscription->net_price;
        $domainInvoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $domainInvoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $domainInvoiceItem->group_label = null;
        $domainInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $domainInvoiceItem->prepaid_reference = null;
        $domainInvoiceItem->save();

        // DNS seeding
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $domainSubscription->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $dnsOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $dnsInvoiceItem = new Invoice();
        $dnsInvoiceItem->subscription_id = $dnsSubscription->id;
        $dnsInvoiceItem->customer_id = $customer->id;
        $dnsInvoiceItem->product_id = $dnsProduct->id;
        $dnsInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $dnsInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $dnsInvoiceItem->ledger_code = $dnsProduct->productGroup->ledger_code;
        $dnsInvoiceItem->paid = false;
        $dnsInvoiceItem->start_date = $dnsSubscription->start_date;
        $dnsInvoiceItem->end_date = $dnsSubscription->next_billing_date;
        $dnsInvoiceItem->period = $dnsSubscription->contract_period;
        $dnsInvoiceItem->gross_price = $dnsSubscription->gross_price;
        $dnsInvoiceItem->net_price = $dnsSubscription->net_price;
        $dnsInvoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $dnsInvoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $dnsInvoiceItem->group_label = null;
        $dnsInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $dnsInvoiceItem->prepaid_reference = null;
        $dnsInvoiceItem->save();

        // Trustee addon
        $product = $this->referenceRepo->get(ProductReference::DOMAIN_ADD_ON_TRUSTEE, Product::class);
        $price = $this->referenceRepo->get(ProductReference::DOMAIN_ADD_ON_TRUSTEE_PRICE, ProductPriceComponent::class);

        $trusteeSubscription = new Subscription();
        $trusteeSubscription->uuid = Uuid::uuid4()->toString();
        $trusteeSubscription->customer_id = $customer->id;
        $trusteeSubscription->product_uuid = $product->uuid;
        $trusteeSubscription->domain = $domainOrderItem->domain;
        $trusteeSubscription->contract_period = $price->contract_period;
        $trusteeSubscription->billing_period = $price->billing_period;
        $trusteeSubscription->net_price = $price->price;
        $trusteeSubscription->gross_price = $price->price;
        $trusteeSubscription->technical_status = TechnicalStatus::OK->value;
        $trusteeSubscription->start_date = CarbonImmutable::yesterday();
        $trusteeSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $trusteeSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $trusteeSubscription->parent_subscription_id = $domainSubscription->id;
        $trusteeSubscription->save();

        $trusteeSubscriptionPrice = new SubscriptionPrice();
        $trusteeSubscriptionPrice->subscription_id = $trusteeSubscription->id;
        $trusteeSubscriptionPrice->valid_from = $trusteeSubscription->start_date;
        $trusteeSubscriptionPrice->net_price = $trusteeSubscription->net_price;
        $trusteeSubscriptionPrice->save();

        $trusteeSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $trusteeSubscriptionPriceComponent->subscription_price_id = $trusteeSubscriptionPrice->id;
        $trusteeSubscriptionPriceComponent->type = $price->type;
        $trusteeSubscriptionPriceComponent->percentage_discount = null;
        $trusteeSubscriptionPriceComponent->fixed_discount = null;
        $trusteeSubscriptionPriceComponent->fixed_price = $trusteeSubscription->net_price;
        $trusteeSubscriptionPriceComponent->new_price = $trusteeSubscription->net_price;
        $trusteeSubscriptionPriceComponent->order_applied = 1;
        $trusteeSubscriptionPriceComponent->save();

        $trusteeSubscription->subscription_price_id = $trusteeSubscriptionPrice->id;
        $trusteeSubscription->save();

        $trusteeInvoiceItem = new Invoice();
        $trusteeInvoiceItem->subscription_id = $trusteeSubscription->id;
        $trusteeInvoiceItem->customer_id = $customer->id;
        $trusteeInvoiceItem->product_id = $product->id;
        $trusteeInvoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $trusteeInvoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $trusteeInvoiceItem->ledger_code = $product->productGroup->ledger_code;
        $trusteeInvoiceItem->paid = false;
        $trusteeInvoiceItem->start_date = $trusteeSubscription->start_date;
        $trusteeInvoiceItem->end_date = $trusteeSubscription->next_billing_date;
        $trusteeInvoiceItem->period = $trusteeSubscription->contract_period;
        $trusteeInvoiceItem->gross_price = $trusteeSubscription->gross_price;
        $trusteeInvoiceItem->net_price = $trusteeSubscription->net_price;
        $trusteeInvoiceItem->title = $trusteeSubscription->domain ?? $trusteeSubscription->product->name;
        $trusteeInvoiceItem->description = sprintf('%s for %s', $trusteeSubscription->product->name, $trusteeSubscription->domain);
        $trusteeInvoiceItem->group_label = null;
        $trusteeInvoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $trusteeInvoiceItem->prepaid_reference = null;
        $trusteeInvoiceItem->save();
    }

    private function provisioningHostingDirectAdmin(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_PROVISIONING, Product::class);

        $price = $this->referenceRepo->get(ProductReference::HOSTING_PROVISIONING_REGISTRATION_PRICE, ProductPriceComponent::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_DIRECT_ADMIN, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'provisioning-hosting-directadmin.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::uuid4();
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($hostingSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $hostingOrderItem->domain, 'servicePlan' => 'invalid'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $provisionRequest->request_type = ProvisionType::HOSTING;
        $provisionRequest->provision_provider = ProvisionProvider::PLESK;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'servicePlan' => ['invalid_service_plan' => 'Invalid service plan given.'],
                    'username' => ['required' => 'Missing username.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::uuid4();
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($hostingSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $hostingOrderItem->domain, 'servicePlan' => 'valid', 'ipv4' => '12.34.12.34'], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $retryProvisionRequest->request_type = ProvisionType::HOSTING;
        $retryProvisionRequest->provision_provider = ProvisionProvider::PLESK;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created hosting successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $hostingDeployment = new ProvisioningHostingDeployment();
        $hostingDeployment->domain = $hostingOrderItem->domain;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->uuid = Uuid::uuid4();
        $hostingDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $hostingDeployment->save();

        $daHostingDeployment = new DirectAdminHostingDeployment();
        $daHostingDeployment->uuid = Uuid::uuid4();
        $daHostingDeployment->username = 'daUserName';
        $daHostingDeployment->default_domain = $hostingOrderItem->domain;
        $daHostingDeployment->hosting_deployment_id = $hostingDeployment->id;
        $daHostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function provisioningHostingPlesk(Customer $customer): void
    {
        $product = $this->referenceRepo->get(ProductReference::HOSTING_PROVISIONING, Product::class);

        $price = $this->referenceRepo->get(ProductReference::HOSTING_PROVISIONING_REGISTRATION_PRICE, ProductPriceComponent::class);
        $server = $this->referenceRepo->get(ProductReference::HOSTING_SERVER_PLESK, Server::class);

        $domainProduct = $this->referenceRepo->get(ProductReference::DOMAIN_NL, Product::class);
        $domainPrice = $this->referenceRepo->get(ProductReference::DOMAIN_NL_REGISTRATION_PRICE, ProductPriceComponent::class);
        $domainProvider = $this->referenceRepo->get(ProductReference::DOMAIN_PROVIDER_DEFAULT, Provider::class);
        $domainContact = $this->referenceRepo->get(ScenarioReference::TEST_KEES_DOMAIN_CONTACT, DomainContact::class);

        $dnsProduct = $this->referenceRepo->get(ProductReference::DNS_FREE, Product::class);
        $dnsPrice = $this->referenceRepo->get(ProductReference::DNS_FREE_REGISTRATION_PRICE, ProductPriceComponent::class);
        $dnsNameserver1 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_1, DnsNameserver::class);
        $dnsNameserver2 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_2, DnsNameserver::class);
        $dnsNameserver3 = $this->referenceRepo->get(ProductReference::DNS_NAMESERVER_3, DnsNameserver::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price + $domainPrice->price + $dnsPrice->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $hostingOrderItem = new OrderLineItem();
        $hostingOrderItem->order_id = $order->id;
        $hostingOrderItem->domain = 'provisioning-hosting-plesk.nl';
        $hostingOrderItem->product_uuid = $product->uuid;
        $hostingOrderItem->product_name = $product->name;
        $hostingOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $hostingOrderItem->gross_price = $price->price;
        $hostingOrderItem->net_price = $price->price;
        $hostingOrderItem->billing_period = $price->billing_period;
        $hostingOrderItem->contract_period = $price->contract_period;
        $hostingOrderItem->processed_at = CarbonImmutable::now();
        $hostingOrderItem->should_invoice = true;
        $hostingOrderItem->save();

        $hostingSubscription = new Subscription();
        $hostingSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $hostingSubscription->uuid = Uuid::uuid4()->toString();
        $hostingSubscription->customer_id = $customer->id;
        $hostingSubscription->product_uuid = $product->uuid;
        $hostingSubscription->domain = $hostingOrderItem->domain;
        $hostingSubscription->contract_period = $price->contract_period;
        $hostingSubscription->billing_period = $price->billing_period;
        $hostingSubscription->net_price = $hostingOrderItem->net_price;
        $hostingSubscription->gross_price = $hostingOrderItem->gross_price;
        $hostingSubscription->technical_status = TechnicalStatus::OK->value;
        $hostingSubscription->start_date = CarbonImmutable::yesterday();
        $hostingSubscription->next_billing_date = $hostingSubscription->start_date->addMonths($hostingSubscription->billing_period);
        $hostingSubscription->end_date = $hostingSubscription->start_date->addMonths($hostingSubscription->contract_period);
        $hostingSubscription->save();

        $hostingSubscriptionPrice = new SubscriptionPrice();
        $hostingSubscriptionPrice->subscription_id = $hostingSubscription->id;
        $hostingSubscriptionPrice->valid_from = $hostingSubscription->start_date;
        $hostingSubscriptionPrice->net_price = $hostingSubscription->net_price;
        $hostingSubscriptionPrice->save();

        $hostingSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $hostingSubscriptionPriceComponent->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscriptionPriceComponent->type = $price->type;
        $hostingSubscriptionPriceComponent->percentage_discount = null;
        $hostingSubscriptionPriceComponent->fixed_discount = null;
        $hostingSubscriptionPriceComponent->fixed_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->new_price = $hostingSubscription->net_price;
        $hostingSubscriptionPriceComponent->order_applied = 1;
        $hostingSubscriptionPriceComponent->save();

        $hostingSubscription->subscription_price_id = $hostingSubscriptionPrice->id;
        $hostingSubscription->save();

        $hostingOrderItem->subscription_uuid = $hostingSubscription->uuid;
        $hostingOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::uuid4();
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($hostingSubscription->uuid);
        $provisionRequest->request_data = json_encode(['domain' => $hostingOrderItem->domain, 'servicePlan' => 'invalid'], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $provisionRequest->request_type = ProvisionType::HOSTING;
        $provisionRequest->provision_provider = ProvisionProvider::PLESK;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'servicePlan' => ['invalid_service_plan' => 'Invalid service plan given.'],
                    'ipv4' => ['required' => 'Missing Ipv4.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::uuid4();
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($hostingSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['domain' => $hostingOrderItem->domain, 'servicePlan' => 'valid', 'ipv4' => '12.34.45.67'], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_HOSTING;
        $retryProvisionRequest->request_type = ProvisionType::HOSTING;
        $retryProvisionRequest->provision_provider = ProvisionProvider::PLESK;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'created hosting successfully'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $hostingDeployment = new ProvisioningHostingDeployment();
        $hostingDeployment->domain = $hostingOrderItem->domain;
        $hostingDeployment->server_id = $server->id;
        $hostingDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $hostingDeployment->uuid = Uuid::uuid4();
        $hostingDeployment->save();

        $pleskHostingDeployment = new PleskHostingDeployment();
        $pleskHostingDeployment->uuid = Uuid::uuid4();
        $pleskHostingDeployment->customer_name = 'plesk_customername';
        $pleskHostingDeployment->subscription_domain = $hostingOrderItem->domain;
        $pleskHostingDeployment->hosting_deployment_id = $hostingDeployment->id;
        $pleskHostingDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $hostingSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $hostingSubscription->start_date;
        $invoiceItem->end_date = $hostingSubscription->next_billing_date;
        $invoiceItem->period = $hostingSubscription->contract_period;
        $invoiceItem->net_price = $hostingSubscription->net_price;
        $invoiceItem->gross_price = $hostingSubscription->gross_price;
        $invoiceItem->title = $hostingSubscription->domain ?? $hostingSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $hostingSubscription->product->name, $hostingSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // Domain
        $domainOrderItem = new OrderLineItem();
        $domainOrderItem->order_id = $order->id;
        $domainOrderItem->domain = $hostingOrderItem->domain;
        $domainOrderItem->product_uuid = $domainProduct->uuid;
        $domainOrderItem->product_name = $domainProduct->name;
        $domainOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $domainOrderItem->gross_price = $domainPrice->price;
        $domainOrderItem->net_price = $domainPrice->price;
        $domainOrderItem->billing_period = $domainPrice->billing_period;
        $domainOrderItem->contract_period = $domainPrice->contract_period;
        $domainOrderItem->should_invoice = true;
        $domainOrderItem->save();

        $domainSubscription = new Subscription();
        $domainSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $domainSubscription->uuid = Uuid::uuid4()->toString();
        $domainSubscription->customer_id = $customer->id;
        $domainSubscription->product_uuid = $domainProduct->uuid;
        $domainSubscription->domain = $domainOrderItem->domain;
        $domainSubscription->contract_period = $domainPrice->contract_period;
        $domainSubscription->billing_period = $domainPrice->billing_period;
        $domainSubscription->net_price = $domainOrderItem->net_price;
        $domainSubscription->gross_price = $domainOrderItem->gross_price;
        $domainSubscription->technical_status = TechnicalStatus::OK->value;
        $domainSubscription->start_date = CarbonImmutable::yesterday();
        $domainSubscription->next_billing_date = $domainSubscription->start_date->addMonths($domainSubscription->billing_period);
        $domainSubscription->end_date = $domainSubscription->start_date->addMonths($domainSubscription->contract_period);
        $domainSubscription->save();

        $domainSubscriptionPrice = new SubscriptionPrice();
        $domainSubscriptionPrice->subscription_id = $domainSubscription->id;
        $domainSubscriptionPrice->valid_from = $domainSubscription->start_date;
        $domainSubscriptionPrice->net_price = $domainSubscription->net_price;
        $domainSubscriptionPrice->save();

        $domainSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $domainSubscriptionPriceComponent->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscriptionPriceComponent->type = $domainPrice->type;
        $domainSubscriptionPriceComponent->percentage_discount = null;
        $domainSubscriptionPriceComponent->fixed_discount = null;
        $domainSubscriptionPriceComponent->fixed_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->new_price = $domainSubscription->net_price;
        $domainSubscriptionPriceComponent->order_applied = 1;
        $domainSubscriptionPriceComponent->save();

        $domainSubscription->subscription_price_id = $domainSubscriptionPrice->id;
        $domainSubscription->save();

        $domainOrderItem->subscription_uuid = $domainSubscription->uuid;
        $domainOrderItem->save();

        $domainDeployment = new DomainDeployment();
        $domainDeployment->subscription_uuid = $domainSubscription->uuid;
        $domainDeployment->provider_id = $domainProvider->id;
        $domainDeployment->last_result = (string) json_encode([]);
        $domainDeployment->last_result_received = CarbonImmutable::now();
        $domainDeployment->dnssec_enabled = false;
        $domainDeployment->private_whois_enabled = false;
        $domainDeployment->contact_owner_id = $domainContact->id;
        $domainDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $domainSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $domainProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $domainProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $domainSubscription->start_date;
        $invoiceItem->end_date = $domainSubscription->next_billing_date;
        $invoiceItem->period = $domainSubscription->contract_period;
        $invoiceItem->net_price = $domainSubscription->net_price;
        $invoiceItem->gross_price = $domainSubscription->gross_price;
        $invoiceItem->title = $domainSubscription->domain ?? $domainSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $domainSubscription->product->name, $domainSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();

        // DNS
        $dnsOrderItem = new OrderLineItem();
        $dnsOrderItem->order_id = $order->id;
        $dnsOrderItem->domain = $hostingOrderItem->domain;
        $dnsOrderItem->product_uuid = $dnsProduct->uuid;
        $dnsOrderItem->product_name = $dnsProduct->name;
        $dnsOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $dnsOrderItem->gross_price = $dnsPrice->price;
        $dnsOrderItem->net_price = $dnsPrice->price;
        $dnsOrderItem->billing_period = $dnsPrice->billing_period;
        $dnsOrderItem->contract_period = $dnsPrice->contract_period;
        $dnsOrderItem->processed_at = CarbonImmutable::now();
        $dnsOrderItem->should_invoice = true;
        $dnsOrderItem->save();

        $dnsSubscription = new Subscription();
        $dnsSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $dnsSubscription->uuid = Uuid::uuid4()->toString();
        $dnsSubscription->parent_subscription_id = $domainSubscription->id;
        $dnsSubscription->customer_id = $customer->id;
        $dnsSubscription->product_uuid = $dnsProduct->uuid;
        $dnsSubscription->domain = $dnsOrderItem->domain;
        $dnsSubscription->contract_period = $domainSubscription->contract_period;
        $dnsSubscription->billing_period = $domainSubscription->billing_period;
        $dnsSubscription->net_price = $dnsOrderItem->net_price;
        $dnsSubscription->gross_price = $dnsOrderItem->gross_price;
        $dnsSubscription->technical_status = TechnicalStatus::OK->value;
        $dnsSubscription->start_date = $domainSubscription->start_date;
        $dnsSubscription->next_billing_date = $domainSubscription->next_billing_date;
        $dnsSubscription->end_date = $domainSubscription->end_date;
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

        $dnsOrderItem->parent_subscription_uuid = $domainSubscription->uuid;
        $dnsOrderItem->subscription_uuid = $dnsSubscription->uuid;
        $domainOrderItem->save();

        $dnsDeployment = new DnsDeployment();
        $dnsDeployment->subscription_uuid = $dnsSubscription->uuid;
        $dnsDeployment->last_result = (string) json_encode([]);
        $dnsDeployment->last_result_received = CarbonImmutable::now();
        $dnsDeployment->nameserver_type = NameserverType::INTERNAL;
        $dnsDeployment->save();

        $dnsDeployment->dnsNameservers()->saveMany([
            $dnsNameserver1,
            $dnsNameserver2,
            $dnsNameserver3,
        ]);

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $dnsSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $product->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $product->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $dnsSubscription->start_date;
        $invoiceItem->end_date = $dnsSubscription->next_billing_date;
        $invoiceItem->period = $dnsSubscription->contract_period;
        $invoiceItem->net_price = $dnsSubscription->net_price;
        $invoiceItem->gross_price = $dnsSubscription->gross_price;
        $invoiceItem->title = $dnsSubscription->domain ?? $dnsSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $dnsSubscription->product->name, $dnsSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function puzzelCallbackRequests(Customer $customer): void
    {
        $firstSlot = $this->referenceRepo->get(
            ScenarioReference::TEST_KEES_PUZZEL_FIRST_CALLBACK_TIMESLOT,
            PuzzelCallbackTimeslot::class
        );

        $slotTime = $firstSlot->start_timeslot;
        $desiredTime = CarbonImmutable::tomorrow()
            ->setTime(
                hour: $slotTime->hour,
                minute: $slotTime->minute,
                second: $slotTime->second
            );

        $request = new PuzzelCallbackRequest();
        $request->uuid = Uuid::uuid4();
        $request->customer_id = $customer->id;
        $request->puzzel_callback_timeslot_id = $firstSlot->id;
        $request->phone_number = '+31612345678';
        $request->name = 'Question about invoice';
        $request->request_category = 'billing';
        $request->request_description = 'I would like to be called back regarding an invoice';
        $request->desired_callback_time = $desiredTime;
        $request->save();
    }

    private function puzzelBlockedDates(): void
    {
        $nextWorkingDay = CarbonImmutable::now()->nextWeekday();

        $blockedDate = new PuzzelBlockedDate();
        // We check if the date is not tomorrow because we already seeded a callback request on this date.
        $blockedDate->date = $nextWorkingDay->isTomorrow() ? $nextWorkingDay->addDays(2) : $nextWorkingDay;
        $blockedDate->reason = 'Company has team building.';
        $blockedDate->save();

        $blockedDateNoReason = new PuzzelBlockedDate();
        // We check if the date is not tomorrow because we already seeded a callback request on this date.
        $blockedDateNoReason->date = $nextWorkingDay->isTomorrow() ? $nextWorkingDay->addDays(3) : $nextWorkingDay->addDay();
        $blockedDateNoReason->save();
    }

    private function customerRetentionOffers(Customer $customer): void
    {
        $hostingProduct = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);
        $hostingPrice = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC_REGISTRATION_PRICE, ProductPriceComponent::class);

        $retentionSubscription = new Subscription();
        $retentionSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $retentionSubscription->uuid = Uuid::uuid4()->toString();
        $retentionSubscription->customer_id = $customer->id;
        $retentionSubscription->product_uuid = $hostingProduct->uuid;
        $retentionSubscription->contract_period = $hostingPrice->contract_period;
        $retentionSubscription->billing_period = $hostingPrice->billing_period;
        $retentionSubscription->net_price = $hostingPrice->price;
        $retentionSubscription->gross_price = $hostingPrice->price;
        $retentionSubscription->technical_status = TechnicalStatus::OK->value;
        $retentionSubscription->start_date = CarbonImmutable::now()->subMonths(6);
        $retentionSubscription->next_billing_date = $retentionSubscription->start_date->addMonths($retentionSubscription->billing_period);
        $retentionSubscription->end_date = $retentionSubscription->start_date->addMonths($retentionSubscription->contract_period);
        $retentionSubscription->save();

        $retentionSubscriptionPrice = new SubscriptionPrice();
        $retentionSubscriptionPrice->subscription_id = $retentionSubscription->id;
        $retentionSubscriptionPrice->valid_from = $retentionSubscription->start_date;
        $retentionSubscriptionPrice->net_price = $retentionSubscription->net_price;
        $retentionSubscriptionPrice->save();

        $retentionSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $retentionSubscriptionPriceComponent->subscription_price_id = $retentionSubscriptionPrice->id;
        $retentionSubscriptionPriceComponent->type = $hostingPrice->type;
        $retentionSubscriptionPriceComponent->percentage_discount = null;
        $retentionSubscriptionPriceComponent->fixed_discount = null;
        $retentionSubscriptionPriceComponent->fixed_price = $retentionSubscription->net_price;
        $retentionSubscriptionPriceComponent->new_price = $retentionSubscription->net_price;
        $retentionSubscriptionPriceComponent->order_applied = 1;
        $retentionSubscriptionPriceComponent->save();

        $retentionSubscription->subscription_price_id = $retentionSubscriptionPrice->id;
        $retentionSubscription->save();

        $employeeUuid = Uuid::uuid4();
        $employeeEmail = 'retention-agent@sandwave.io';

        $consumerOffer = new CustomerRetentionOffer();
        $consumerOffer->created_by_metadata = new IdentityMetadataDTO(uuid: $employeeUuid, email: $employeeEmail);
        $consumerOffer->customer_type = CustomerType::CONSUMER;
        $consumerOffer->subscription_id = $retentionSubscription->id;
        $consumerOffer->selected_action = SelectedAction::TK_OPTION_1;
        $consumerOffer->puzzel_ticket_id = '100001';
        $consumerOffer->effective_at = CarbonImmutable::now();
        $consumerOffer->save();

        $businessOffer = new CustomerRetentionOffer();
        $businessOffer->created_by_metadata = new IdentityMetadataDTO(uuid: $employeeUuid, email: $employeeEmail);
        $businessOffer->customer_type = CustomerType::BUSINESS;
        $businessOffer->subscription_id = $retentionSubscription->id;
        $businessOffer->selected_action = SelectedAction::DM_OPTION_1;
        $businessOffer->puzzel_ticket_id = '100002';
        $businessOffer->effective_at = CarbonImmutable::now();
        $businessOffer->save();
    }

    private function provisionBackupAcronis(Customer $customer): void
    {
        $backupProduct = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_100, Product::class);
        $price = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_100_REGISTRATION_PRICE, ProductPriceComponent::class);

        $order = new Order();
        $order->uuid = Uuid::uuid4()->toString();
        $order->customer_id = $customer->id;
        $order->status = OrderStatus::PROCESSED;
        $order->payment_method = PaymentMethod::INVOICE;
        $order->administration_fees = 0;
        $order->total_price = $price->price;
        $order->ordered_by_uuid = $customer->uuid;
        $order->ordered_by_metadata = (string) json_encode([
            'email' => $customer->email,
            'schemaId' => SchemaId::CUSTOMER,
        ]);
        $order->is_invoiced = true;
        $order->save();

        $backupOrderItem = new OrderLineItem();
        $backupOrderItem->order_id = $order->id;
        $backupOrderItem->product_uuid = $backupProduct->uuid;
        $backupOrderItem->product_name = $backupProduct->name;
        $backupOrderItem->status = OrderLineItemStatus::REGISTRATION;
        $backupOrderItem->gross_price = $price->price;
        $backupOrderItem->net_price = $price->price;
        $backupOrderItem->billing_period = $price->billing_period;
        $backupOrderItem->contract_period = $price->contract_period;
        $backupOrderItem->processed_at = CarbonImmutable::now();
        $backupOrderItem->should_invoice = true;
        $backupOrderItem->save();

        $backupSubscription = new Subscription();
        $backupSubscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $backupSubscription->uuid = Uuid::uuid4()->toString();
        $backupSubscription->customer_id = $customer->id;
        $backupSubscription->product_uuid = $backupProduct->uuid;
        $backupSubscription->contract_period = $price->contract_period;
        $backupSubscription->billing_period = $price->billing_period;
        $backupSubscription->net_price = $backupOrderItem->net_price;
        $backupSubscription->gross_price = $backupOrderItem->gross_price;
        $backupSubscription->technical_status = TechnicalStatus::OK->value;
        $backupSubscription->start_date = CarbonImmutable::yesterday();
        $backupSubscription->next_billing_date = $backupSubscription->start_date->addMonths($backupSubscription->billing_period);
        $backupSubscription->end_date = $backupSubscription->start_date->addMonths($backupSubscription->contract_period);
        $backupSubscription->save();

        $backupSubscriptionPrice = new SubscriptionPrice();
        $backupSubscriptionPrice->subscription_id = $backupSubscription->id;
        $backupSubscriptionPrice->valid_from = $backupSubscription->start_date;
        $backupSubscriptionPrice->net_price = $backupSubscription->net_price;
        $backupSubscriptionPrice->save();

        $backupSubscriptionPriceComponent = new SubscriptionPriceComponent();
        $backupSubscriptionPriceComponent->subscription_price_id = $backupSubscriptionPrice->id;
        $backupSubscriptionPriceComponent->type = $price->type;
        $backupSubscriptionPriceComponent->percentage_discount = null;
        $backupSubscriptionPriceComponent->fixed_discount = null;
        $backupSubscriptionPriceComponent->fixed_price = $backupSubscription->net_price;
        $backupSubscriptionPriceComponent->new_price = $backupSubscription->net_price;
        $backupSubscriptionPriceComponent->order_applied = 1;
        $backupSubscriptionPriceComponent->save();

        $backupSubscription->subscription_price_id = $backupSubscriptionPrice->id;
        $backupSubscription->save();

        $backupOrderItem->subscription_uuid = $backupSubscription->uuid;
        $backupOrderItem->save();

        $provisionRequest = new ProvisioningRequest();
        $provisionRequest->context_uuid = Uuid::fromString($backupSubscription->uuid);
        $provisionRequest->uuid = Uuid::uuid4();
        $provisionRequest->tag = Uuid::fromString($backupSubscription->uuid);
        $provisionRequest->request_data = json_encode(['language' => 'en', 'email' => 'test.kees-not-valid', 'firstname' => 'Test', 'username' => 'tkees', 'cloudStorageInGb' => 50.0], JSON_THROW_ON_ERROR);
        $provisionRequest->request_name = ProvisionRequestName::CREATE_BACKUP;
        $provisionRequest->request_type = ProvisionType::BACKUP;
        $provisionRequest->provision_provider = ProvisionProvider::ACRONIS;
        $provisionRequest->save();

        $provisionResultFailed = new ProvisioningResult();
        $provisionResultFailed->uuid = Uuid::uuid4();
        $provisionResultFailed->request_id = $provisionRequest->id;
        $provisionResultFailed->response = json_encode([
            'status' => ProvisionStatus::VALIDATION_ERROR->value,
            'message' => 'Validation failed',
            'validation_result' => [
                'isValid' => false,
                'messages' => [
                    'email' => ['invalid' => 'Invalid email.'],
                    'lastname' => ['required' => 'Lastname is required.'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $provisionResultFailed->status = ProvisionStatus::VALIDATION_ERROR;
        $provisionResultFailed->created_at = CarbonImmutable::now();
        $provisionResultFailed->updated_at = CarbonImmutable::now();
        $provisionResultFailed->save();

        $retryProvisionRequest = new ProvisioningRequest();
        $retryProvisionRequest->context_uuid = Uuid::fromString($backupSubscription->uuid);
        $retryProvisionRequest->uuid = Uuid::uuid4();
        $retryProvisionRequest->tag = Uuid::fromString($backupSubscription->uuid);
        $retryProvisionRequest->request_data = json_encode(['language' => 'en', 'email' => 'test.kees@valid.nl', 'firstname' => 'Test', 'username' => 'tkees', 'lastname' => 'Kees', 'cloudStorageInGb' => 50.0], JSON_THROW_ON_ERROR);
        $retryProvisionRequest->request_name = ProvisionRequestName::CREATE_BACKUP;
        $retryProvisionRequest->request_type = ProvisionType::BACKUP;
        $retryProvisionRequest->provision_provider = ProvisionProvider::ACRONIS;
        $retryProvisionRequest->retry_of_request_id = $provisionRequest->id;
        $retryProvisionRequest->requested_by_uuid = Uuid::uuid4();
        $retryProvisionRequest->created_at = $provisionRequest->created_at?->addMinute();
        $retryProvisionRequest->updated_at = $provisionRequest->updated_at?->addMinute();
        $retryProvisionRequest->save();

        $provisionResult = new ProvisioningResult();
        $provisionResult->uuid = Uuid::uuid4();
        $provisionResult->request_id = $retryProvisionRequest->id;
        $provisionResult->response = json_encode(['status' => ProvisionStatus::SUCCESS, 'message' => 'acronis backup successfully provisioned'], JSON_THROW_ON_ERROR);
        $provisionResult->status = ProvisionStatus::SUCCESS;
        $provisionResult->created_at = $retryProvisionRequest->created_at?->addMinute();
        $provisionResult->updated_at = $retryProvisionRequest->updated_at?->addMinute();
        $provisionResult->save();

        $acronisProvider = $this->referenceRepo->get(
            ProductReference::ACRONIS_PROVIDER_YOURHOSTING,
            AcronisProvider::class
        );

        $backupDeployment = new BackupDeployment();
        $backupDeployment->uuid = Uuid::uuid4();
        $backupDeployment->origin_provisioning_request_id = $provisionRequest->id;
        $backupDeployment->save();

        $acronisBackupDeployment = new AcronisBackupDeployment();
        $acronisBackupDeployment->uuid = Uuid::uuid4();
        $acronisBackupDeployment->backup_deployment_id = $backupDeployment->id;
        $acronisBackupDeployment->acronis_provider_id = $acronisProvider->id;

        $acronisBackupDeployment->tenant_uuid = Uuid::uuid4();
        $acronisBackupDeployment->user_uuid = Uuid::uuid4();
        $acronisBackupDeployment->save();

        $invoiceItem = new Invoice();
        $invoiceItem->subscription_id = $backupSubscription->id;
        $invoiceItem->customer_id = $customer->id;
        $invoiceItem->product_id = $backupProduct->id;
        $invoiceItem->vat_code = ($customer->address->country_code ?? 'NL') . (int) $customer->vat_rate;
        $invoiceItem->vat_rate = $customer->vat_rate ?? 0;
        $invoiceItem->ledger_code = $backupProduct->productGroup->ledger_code;
        $invoiceItem->paid = false;
        $invoiceItem->start_date = $backupSubscription->start_date;
        $invoiceItem->end_date = $backupSubscription->next_billing_date;
        $invoiceItem->period = $backupSubscription->contract_period;
        $invoiceItem->net_price = $backupSubscription->net_price;
        $invoiceItem->gross_price = $backupSubscription->gross_price;
        $invoiceItem->title = $backupSubscription->domain ?? $backupSubscription->product->name;
        $invoiceItem->description = sprintf('%s for %s', $backupSubscription->product->name, $backupSubscription->domain);
        $invoiceItem->group_label = null;
        $invoiceItem->type = InvoiceLine::TYPE_DEFAULT;
        $invoiceItem->prepaid_reference = null;
        $invoiceItem->save();
    }

    private function subscriptionChanges(Customer $customer): void
    {
        $this->hostingSubscriptionChanges($customer);
        $this->backupSubscriptionChanges($customer);
    }

    private function hostingSubscriptionChanges(Customer $customer): void
    {
        $webBasic = $this->referenceRepo->get(ProductReference::HOSTING_WEB_BASIC, Product::class);
        $webGrow = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW, Product::class);
        $price = $this->referenceRepo->get(ProductReference::HOSTING_WEB_GROW_REGISTRATION_PRICE, ProductPriceComponent::class);

        $subscription = $this->createSubscriptionForChanges($customer, $webGrow, $price, 'subscription-changes-hosting.nl');

        $this->createSubscriptionChange(
            $subscription,
            $webBasic,
            $webGrow,
            ProductChangeType::UPGRADE,
            SubscriptionChangeStatus::COMPLETED,
            null,
            null
        );

        $this->createSubscriptionChange(
            $subscription,
            $webGrow,
            $webBasic,
            ProductChangeType::DOWNGRADE,
            SubscriptionChangeStatus::REQUESTED,
            null,
            null
        );

        $this->createSubscriptionChange(
            $subscription,
            $webGrow,
            $webBasic,
            ProductChangeType::DOWNGRADE,
            SubscriptionChangeStatus::EXECUTION_FAILED,
            500,
            'Downgrade failed because the target package does not meet the requirements'
        );
    }

    private function backupSubscriptionChanges(Customer $customer): void
    {
        $acronis50 = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_50, Product::class);
        $acronis100 = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_100, Product::class);
        $acronis250 = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_250, Product::class);
        $price = $this->referenceRepo->get(ProductReference::BACKUP_ACRONIS_100_REGISTRATION_PRICE, ProductPriceComponent::class);

        $subscription = $this->createSubscriptionForChanges($customer, $acronis100, $price, null);

        $this->createSubscriptionChange(
            $subscription,
            $acronis50,
            $acronis100,
            ProductChangeType::UPGRADE,
            SubscriptionChangeStatus::EXECUTION_FAILED,
            422,
            'Backup upgrade failed'
        );

        $this->createSubscriptionChange(
            $subscription,
            $acronis100,
            $acronis250,
            ProductChangeType::UPGRADE,
            SubscriptionChangeStatus::EXECUTION_FAILED,
            0,
            'Something went wrong'
        );
    }

    private function createSubscriptionForChanges(
        Customer $customer,
        Product $product,
        ProductPriceComponent $price,
        ?string $domain
    ): Subscription {
        $subscription = new Subscription();
        $subscription->administrative_status = AdministrativeStatus::ACTIVE->value;
        $subscription->uuid = Uuid::uuid4()->toString();
        $subscription->customer_id = $customer->id;
        $subscription->product_uuid = $product->uuid;
        $subscription->domain = $domain;
        $subscription->contract_period = $price->contract_period;
        $subscription->billing_period = $price->billing_period;
        $subscription->net_price = $price->price;
        $subscription->gross_price = $price->price;
        $subscription->technical_status = TechnicalStatus::OK->value;
        $subscription->start_date = CarbonImmutable::yesterday();
        $subscription->next_billing_date = $subscription->start_date->addMonths($subscription->billing_period);
        $subscription->end_date = $subscription->start_date->addMonths($subscription->contract_period);
        $subscription->save();

        return $subscription;
    }

    private function createSubscriptionChange(
        Subscription $subscription,
        Product $fromProduct,
        Product $toProduct,
        ProductChangeType $type,
        SubscriptionChangeStatus $status,
        ?int $failureCode,
        ?string $failureMessage
    ): void {
        $change = new SubscriptionChange();
        $change->uuid = Uuid::uuid4();
        $change->subscription_uuid = Uuid::fromString($subscription->uuid);
        $change->from_product_uuid = Uuid::fromString($fromProduct->uuid);
        $change->to_product_uuid = Uuid::fromString($toProduct->uuid);
        $change->type = $type;
        $change->status = $status;
        $change->failure_code = $failureCode;
        $change->failure_message = $failureMessage;
        $change->requested_at = CarbonImmutable::now()->subMinutes(10);
        $change->completed_at = $status === SubscriptionChangeStatus::COMPLETED ? CarbonImmutable::now() : null;
        $change->save();
    }
}
