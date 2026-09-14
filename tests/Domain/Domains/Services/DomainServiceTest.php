<?php

declare(strict_types=1);

namespace Tests\Domain\Domains\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\DTO\DestroyContactResult as Result;
use Waterfront\Domain\Domains\DTO\TransferResult;
use Waterfront\Domain\Domains\Exceptions\DomainContactException;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Services\OpenproviderService as OpenproviderDomainService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Support\Exceptions\NotImplementedException;

#[CoversClass(DomainService::class)]
class DomainServiceTest extends IntegrationTestCase
{
    private DomainService $domainService;

    private Customer $customer;

    private Provider $domainProviderOpen;

    private Provider $domainProviderRtr;

    private Subscription $subscription;

    private DomainContact $domainContact;

    public function setUp(): void
    {
        parent::setUp();

        $this->domainService = self::resolve(DomainService::class);

        $this->customer = new CustomerFactory()->createOne();

        $this->domainProviderOpen = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::OPEN_PROVIDER,
            'enabled' => true,
            'default' => true,
        ]);

        $this->domainProviderRtr = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $productGroup = new ProductGroupFactory()->createOne([
            'name' => 'Domein',
            'slug' => 'extension',
        ]);

        $product = new ProductFactory()->for($productGroup)->createOne();

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'domain' => 'dummyfake.nl',
            'product_uuid' => $product->uuid,
        ]);

        $this->domainContact = new DomainContactFactory()->createOne([
            'email' => 'domain@fake.nl',
            'first_name' => 'Dummy',
            'last_name' => 'tester',
            'customer_id' => $this->customer->getKey(),
        ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $this->subscription->uuid,
            'provider_id' => $this->domainProviderRtr->getKey(),
            'contact_owner_id' => $this->domainContact->getKey(),
        ]);
    }

    #[Test]
    public function unlinkDomainContact(): void
    {
        self::assertDatabaseHas('domain_deployments', [
            'contact_owner_id' => $this->domainContact->id,
        ]);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);

        self::assertDatabaseMissing('domain_deployments', [
            'contact_owner_id' => $this->domainContact->id,
        ]);
    }

    #[Test]
    public function destroyDomainContactSuccessWithoutAttachment(): void
    {
        self::assertDatabaseHas('domain_deployments', [
            'contact_owner_id' => $this->domainContact->id,
        ]);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertTrue($result);
        self::assertFalse(DomainContact::exists());
    }

    #[Test]
    public function destroyDomainContactSuccess(): void
    {
        $externalContact = 345;
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact]);

        $rtrServiceMock = self::createMock(RtrService::class);
        $rtrServiceMock->expects(self::once())->method('destroyContact')->willReturn(new Result(true));

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        self::assertDatabaseHas('domain_deployments', [
            'contact_owner_id' => $this->domainContact->id,
        ]);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertTrue($result);
        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertFalse(DomainContact::exists());
    }

    #[Test]
    public function destroyContactFailedByAttachedDomain(): void
    {
        $externalContact = 345;
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact]);

        $this->expectException(DomainContactException::class);
        $this->expectExceptionMessageIs('Contact owner cannot be deleted because it still has domains attached.');

        $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseHas('domain_contacts', [
            'domain_contact_id' => $this->domainContact->id,
        ]);
    }

    #[Test]
    public function destroyContactFailedByGatewayException(): void
    {
        $externalContact = 345;
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact]);

        $rtrServiceMock = self::createMock(RtrService::class);
        $rtrServiceMock
            ->expects(self::once())
            ->method('destroyContact')
            ->willThrowException(new LogicException('testmessage', 1001));

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertFalse($result);

        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseHas('domain_contacts', [
            'id' => $this->domainContact->id,
            'email' => $this->domainContact->email,
        ]);
    }

    #[Test]
    public function destroyContactWithMultipleAttachedProviders(): void
    {
        $extraSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'dummyfakeTwo.nl',
                'product_uuid' => $this->subscription->product_uuid,
            ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $extraSubscription->uuid,
            'provider_id' => $this->domainProviderOpen->id,
            'contact_owner_id' => $this->domainContact->id,
        ]);

        $externalContact = 345;
        $externalContact2 = 756;
        $this->domainContact->providers()->attach($this->domainProviderOpen, ['external_contact' => $externalContact]);
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact2]);

        $domainServiceMock = self::createMock(OpenproviderDomainService::class);
        $domainServiceMock->expects(self::once())->method('setClient')->willReturnSelf();
        $domainServiceMock->expects(self::once())->method('destroyContact')->willReturn(new Result(true));
        $this->app->instance(
            OpenproviderDomainService::class,
            $domainServiceMock,
        );

        $rtrServiceMock = self::createMock(RtrService::class);
        $rtrServiceMock->expects(self::once())->method('destroyContact')->willReturn(new Result(true));

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertTrue($result);
        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact2,
        ]);
    }

    #[Test]
    public function destroyContactFailedWithMultipleAttachedProviders(): void
    {
        $extraSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'dummyfakeTwo.nl',
                'product_uuid' => $this->subscription->product_uuid,
            ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $extraSubscription->uuid,
            'provider_id' => $this->domainProviderOpen->id,
            'contact_owner_id' => $this->domainContact->id,
        ]);

        $externalContact = 345;
        $externalContact2 = 756;
        $this->domainContact->providers()->attach($this->domainProviderOpen, ['external_contact' => $externalContact]);
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact2]);

        $domainServiceMock = self::createMock(OpenproviderDomainService::class);
        $domainServiceMock->expects(self::once())->method('setClient')->willReturnSelf();
        $domainServiceMock
            ->expects(self::once())
            ->method('destroyContact')
            ->willThrowException(new LogicException('testmessage', 1001));
        $this->app->instance(
            OpenproviderDomainService::class,
            $domainServiceMock,
        );

        $rtrServiceMock = self::createMock(RtrService::class);
        $rtrServiceMock->expects(self::once())->method('destroyContact')->willReturn(new Result(true));

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message): bool {
                $logMessage = 'Failed deleting domain contact at provider: openprovider,';

                return str_contains($message, $logMessage);
            });

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertFalse($result);
        self::assertDatabaseHas('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact2,
        ]);
    }

    #[Test]
    public function destroyContactWithMultipleContactsAttachedToProvider(): void
    {
        $domainContact2 = new DomainContactFactory()->createOne([
            'email' => 'domain@fake2.nl',
            'first_name' => 'Dummy2',
            'last_name' => 'tester2',
            'customer_id' => $this->customer->id,
        ]);

        //extra subscription
        $extraSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'dummyfakeTwo.nl',
                'product_uuid' => $this->subscription->product_uuid,
            ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $extraSubscription->uuid,
            'provider_id' => $this->domainProviderRtr->id,
            'contact_owner_id' => $domainContact2->id,
        ]);

        $externalContact = 345;
        $externalContact2 = 756;
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact]);
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact2]);

        $rtrServiceMock = self::createMock(RtrService::class);
        $rtrServiceMock->expects(self::exactly(2))->method('destroyContact')->willReturn(new Result(true));

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertTrue($result);
        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact2,
        ]);
    }

    #[Test]
    public function destroyContactFailedWithMultipleContactsAttachedToProvider(): void
    {
        $domainContact2 = new DomainContactFactory()->createOne([
            'email' => 'domain@fake2.nl',
            'first_name' => 'Dummy2',
            'last_name' => 'tester2',
            'customer_id' => $this->customer->id,
        ]);

        //extra subscription
        $extraSubscription = new SubscriptionFactory()
            ->withCustomer()
            ->createOne([
                'domain' => 'dummyfakeTwo.nl',
                'product_uuid' => $this->subscription->product_uuid,
            ]);

        new DomainDeploymentFactory()->createOne([
            'subscription_uuid' => $extraSubscription->uuid,
            'provider_id' => $this->domainProviderRtr->id,
            'contact_owner_id' => $domainContact2->id,
        ]);

        $externalContact = 345;
        $externalContact2 = 756;
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact]);
        $this->domainContact->providers()->attach($this->domainProviderRtr, ['external_contact' => $externalContact2]);

        $rtrServiceMock = self::createMock(RtrService::class);
        $matcher = self::exactly(2);
        $rtrServiceMock
            ->expects($matcher)
            ->method('destroyContact')
            ->willReturnCallback(
                fn () => match ($matcher->numberOfInvocations()) {
                    1 => throw new LogicException('testmessage', 1001),
                    2 => new Result(true),
                    default => throw new NotImplementedException(),
                },
            );

        $rtrServiceMock->method('setHandle')->willReturnSelf();

        $rtrServiceMock->method('setClient')->willReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message): bool {
                $logMessage = 'Failed deleting domain contact at provider: realtime_register,';

                return str_contains($message, $logMessage);
            });

        $this->app->instance(
            RtrService::class,
            $rtrServiceMock,
        );

        $this->domainService = self::resolve(DomainService::class);

        $this->domainService->unlinkDomainSubscriptionsFromContactOwner($this->domainContact);
        $result = $this->domainService->destroyContact($this->customer, $this->domainContact);

        self::assertTrue($result);
        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact,
        ]);

        self::assertDatabaseMissing('domain_contact_provider', [
            'domain_contact_id' => $this->domainContact->id,
            'external_contact' => $externalContact2,
        ]);
    }

    #[Test]
    public function transferDomainWithoutExpiryDate(): void
    {
        $domain = 'dont-keep-external-date.tk';
        $transferSecret = 'secretTransferKey';
        $period = 12;
        $externalDate = CarbonImmutable::today()->addMonths(4);

        $subscriptionEndDate = CarbonImmutable::today()->modify('next year');
        $this->subscription->end_date = $subscriptionEndDate;
        $this->subscription->domain = $domain;

        $productGroup = ProductGroup::where([
            'slug' => ProductGroupType::EXTENSION,
        ])->firstOrFail();

        $product = new ProductFactory()->for($productGroup)->createOne();

        $this->subscription->product_uuid = $product->uuid;
        $this->subscription->save();

        $mockDomainProvider = $this->createMock(RtrService::class);

        $this->app->bind(RtrService::class, fn () => $mockDomainProvider);

        $mockDomainProvider->expects(self::exactly(0))->method('retrieveRenewalDate')->willReturn($externalDate);

        $mockDomainProvider
            ->expects(self::once())
            ->method('transfer')
            ->willReturn(new TransferResult(TechnicalStatus::PENDING->value));

        $mockDomainProvider->method('setHandle')->willReturnSelf();

        $mockDomainProvider->method('setClient')->willReturnSelf();

        $this->domainService = self::resolve(DomainService::class);

        $domainDeployment = $this->subscription->domainDeployment;
        self::assertInstanceOf(DomainDeployment::class, $domainDeployment);

        $transferResult = $this->domainService->transfer(
            $domainDeployment,
            $domain,
            $period,
            $this->customer,
            false,
            false,
            $transferSecret,
        );

        $this->subscription = $this->subscription->refresh();

        self::assertEquals($subscriptionEndDate, $this->subscription->end_date);
        self::assertSame(TechnicalStatus::PENDING->value, $transferResult->getStatus());
    }
}
