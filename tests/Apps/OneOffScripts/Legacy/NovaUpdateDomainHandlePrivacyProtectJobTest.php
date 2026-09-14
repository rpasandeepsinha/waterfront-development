<?php

declare(strict_types=1);

namespace Tests\Apps\OneOffScripts\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\OneOffScripts\Legacy\NovaUpdateDomainHandlePrivacyProtectJob;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Repositories\DomainContactRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(NovaUpdateDomainHandlePrivacyProtectJob::class)]
class NovaUpdateDomainHandlePrivacyProtectJobTest extends IntegrationTestCase
{
    private const string DOMAIN = 'example.test';

    private const string DOMAIN_NOT_FOUND = 'unknown.test';

    private const string ANONYMOUS_EMAIL = 'niemand@anonymous.online';

    private Customer $customer;

    private Subscription $subscription;

    private SubscriptionRepository $subscriptionRepository;

    private DomainContactRepository $domainContactRepository;

    private MockObject&LoggerInterface $logger;

    private MockObject&DomainService $domainService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = self::resolve(SubscriptionRepository::class);
        $this->domainContactRepository = self::resolve(DomainContactRepository::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->domainService = self::createMock(DomainService::class);

        $product = ProductFactory::new()->nlDomain()->createOne();

        $this->customer = CustomerFactory::new()->withAddress()->createOne();

        $this->subscription = SubscriptionFactory::new()
            ->for($product)
            ->for($this->customer)
            ->forDomain(self::DOMAIN)
            ->createOne();
    }

    #[Test]
    public function handleLogsWarningWhenSubscriptionNotFound(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Domain {domain.name} does not exist or is no longer active in Waterfront',
                self::arrayHasKey(LoggingContextKeys::ONE_OFF_SCRIPT),
            );

        $this->domainService->expects(self::never())->method('linkContactHandle');

        $job = new NovaUpdateDomainHandlePrivacyProtectJob(domainName: self::DOMAIN_NOT_FOUND, dryRun: false);

        $job->handle(
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
            domainContactRepository: $this->domainContactRepository,
            domainService: $this->domainService,
        );
    }

    #[Test]
    public function handleLogsDryRunInfoAndDoesNotModifyAnything(): void
    {
        $anonymousContact = $this->createAnonymousContact($this->customer);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                '[DRY RUN] Would create DomainContact and link RTR handle for domain {domain.name}',
                self::arrayHasKey(LoggingContextKeys::ONE_OFF_SCRIPT),
            );

        $this->domainService->expects(self::never())->method('linkContactHandle');

        $job = new NovaUpdateDomainHandlePrivacyProtectJob(domainName: self::DOMAIN, dryRun: true);

        $job->handle(
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
            domainContactRepository: $this->domainContactRepository,
            domainService: $this->domainService,
        );

        // Anonymous contact must still be the default owner — nothing changed
        self::assertTrue($anonymousContact->refresh()->default_owner);
        self::assertCount(1, DomainContact::where('customer_id', $this->customer->id)->get());
    }

    #[Test]
    public function handleCreatesContactAndLinksHandleWhenNotDryRun(): void
    {
        $anonymousContact = $this->createAnonymousContact($this->customer);

        $this->domainService
            ->expects(self::once())
            ->method('linkContactHandle')
            ->with(
                [['domain' => self::DOMAIN]],
                self::callback(fn (DomainContact $contact): bool => $contact->email === $this->customer->email),
            );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Created DomainContact and linked RTR handle for domain {domain.name}',
                self::arrayHasKey(LoggingContextKeys::ONE_OFF_SCRIPT),
            );

        $job = new NovaUpdateDomainHandlePrivacyProtectJob(domainName: self::DOMAIN, dryRun: false);

        $job->handle(
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
            domainContactRepository: $this->domainContactRepository,
            domainService: $this->domainService,
        );

        // New contact based on the customer's real data is now the default owner
        $newContact = DomainContact::where('customer_id', $this->customer->id)
            ->where('email', $this->customer->email)
            ->first();

        self::assertNotNull($newContact);
        self::assertTrue($newContact->default_owner);

        // Anonymous contact is no longer the default owner
        self::assertFalse($anonymousContact->refresh()->default_owner);
    }

    #[Test]
    public function handleSkipsWhenContactAlreadyLinked(): void
    {
        self::assertNotNull($this->customer->address);

        // Create a DomainContact that exactly matches what createOrFindDomainContact would produce,
        // so firstOrCreate finds it rather than creating a new one
        $existingContact = DomainContactFactory::new()->createOne([
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'first_name' => $this->customer->first_name,
            'last_name' => $this->customer->last_name,
            'phone_country_code' => $this->customer->phone_country_code,
            'phone_area_code' => $this->customer->phone_area_code,
            'phone_subscriber_number' => $this->customer->phone_subscriber_number,
            'organization' => $this->customer->organization,
            'street_name' => $this->customer->address->street_name,
            'street_number' => $this->customer->address->street_number,
            'zip_code' => $this->customer->address->zip_code,
            'city' => $this->customer->address->city,
            'country_code' => $this->customer->address->country_code,
            'default_owner' => true,
        ]);

        // Deployment is already linked to this contact — simulates a previous successful run
        DomainDeploymentFactory::new()->withRtrProvider()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'contact_owner_id' => $existingContact->id,
        ]);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(
                'Domain {domain.name} already has the correct contact handle linked, skipping',
                self::arrayHasKey(LoggingContextKeys::ONE_OFF_SCRIPT),
            );

        $this->domainService->expects(self::never())->method('linkContactHandle');

        $job = new NovaUpdateDomainHandlePrivacyProtectJob(domainName: self::DOMAIN, dryRun: false);

        $job->handle(
            subscriptionRepository: $this->subscriptionRepository,
            logger: $this->logger,
            domainContactRepository: $this->domainContactRepository,
            domainService: $this->domainService,
        );
    }

    private function createAnonymousContact(Customer $customer): DomainContact
    {
        return DomainContactFactory::new()->createOne([
            'customer_id' => $customer->id,
            'email' => self::ANONYMOUS_EMAIL,
            'first_name' => 'Anoniem',
            'last_name' => 'Anoniem',
            'organization' => 'Anoniem',
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
            'street_name' => 'Anoniem',
            'street_number' => '1',
            'zip_code' => '1111AA',
            'city' => 'Amsterdam',
            'country_code' => 'NL',
            'default_owner' => true,
        ]);
    }
}
