<?php

declare(strict_types=1);

namespace Tests\Domain\Transfers\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TemplateFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Email\Jobs\SendEmail;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Exceptions\TransferException;
use Waterfront\Domain\Transfers\Interfaces\ExecuteTransferInterface;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedSender;
use Waterfront\Domain\Transfers\Models\Transfer;
use Waterfront\Domain\Transfers\Services\ExecuteTransferService;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(ExecuteTransferService::class)]
class ExecuteTransferServiceTest extends IntegrationTestCase
{
    private Customer $receiver;

    private Customer $from;

    private Customer $randomCustomer;

    private ExecuteTransferInterface $executeTransfers;

    private Transfer $transfer;

    public function setUp(): void
    {
        parent::setUp();
        $rtrService = self::createStub(RtrService::class);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        Model::preventLazyLoading(false);

        $this->executeTransfers = self::resolve(ExecuteTransferInterface::class);
        $this->receiver = new CustomerFactory()
            ->withAddress()
            ->createOne(['email' => 'receiver@fakeadress.io']);
        $this->randomCustomer = new CustomerFactory()
            ->withAddress()
            ->createOne(['email' => 'random@fakeadress.io']);
        $this->from = new CustomerFactory()
            ->withAddress()
            ->createOne(['email' => 'from@fakeadress.io']);

        $this->createEmailTemplates();

        $this->transfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->from->id,
            'to_customer_id' => $this->receiver->id,
        ]);
    }

    #[Test]
    public function transferWithoutExecutableSubscriptionsLeftWillComplete(): void
    {
        Queue::fake();
        $this->transfer->accept();
        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        self::assertNotNull($this->transfer->completed_at);
        Queue::assertPushed(SendEmail::class);
    }

    #[Test]
    public function transferNotAcceptedWillResultInException(): void
    {
        Queue::fake();
        $this->expectException(TransferException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Transfer has not been accepted. Transfer ID: {%s}',
            $this->transfer->id,
        ));
        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        self::assertNull($this->transfer->completed_at);
        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function transferForSubscriptionNotOwnedByOriginateCustomerWillResultInException(): void
    {
        $sslGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::SSL]);
        $product = new ProductFactory()->for($sslGroup)->createOne(['slug' => 'ssl_uitgebreide_groene_balk']);
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id' => $this->randomCustomer->id,
        ]);

        Queue::fake();
        $this->expectException(TransferException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Transfer contains subscriptions not owned by the initiating customer. Transfer ID: {%s}',
            $this->transfer->id,
        ));
        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription]);
        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        self::assertNull($this->transfer->completed_at);
        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function transferForSubscriptionOutdatedByNewAcceptedTransferWillResultInException(): void
    {
        $sslGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::SSL]);
        $product = new ProductFactory()->for($sslGroup)->createOne(['slug' => 'ssl_uitgebreide_groene_balk']);
        $subscription = new SubscriptionFactory()->for($product)->createOne([
            'customer_id' => $this->randomCustomer->id,
        ]);

        Queue::fake();
        $this->expectException(TransferException::class);
        $this->expectExceptionMessageIs(sprintf(
            'Transfer contains subscriptions not owned by the initiating customer. Transfer ID: {%s}',
            $this->transfer->id,
        ));
        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription]);

        $alternativeTransfer = new TransferFactory()->createOne([
            'from_customer_id' => $this->from->id,
            'to_customer_id' => $this->receiver->id,
            'created_at' => CarbonImmutable::now()->addMonth(),
        ]);
        $alternativeTransfer->accept();
        $alternativeTransfer->start();
        $alternativeTransfer->complete();
        $subscription->transfers()->save($alternativeTransfer);

        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        self::assertNull($this->transfer->completed_at);
        Queue::assertNotPushed(SendEmail::class);
    }

    #[Test]
    public function transferWithTechnicalTransferExceptionWillComplete(): void
    {
        $extensionGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $product = new ProductFactory()->for($extensionGroup)->createOne(['slug' => 'extension_nl']);
        $subscription = new SubscriptionFactory()->for($product)->createOne(['customer_id' => $this->from->id]);

        Queue::fake();
        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription]);

        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        self::assertNotNull($this->transfer->completed_at);
        Queue::assertPushed(SendEmail::class);
    }

    /**
     * @see ExecuteTransferServiceTest::transferAdministrativelySupportedWillComplete()
     *
     * @return iterable<string,mixed>
     */
    public static function transferAdministrativelyOnlySupported(): iterable
    {
        yield 'Transfer will succeed for product group HOSTING' => ['slug' => ProductGroupType::HOSTING];
        yield 'Transfer will succeed for product group SSL' => ['slug' => ProductGroupType::SSL];
        yield 'Transfer will succeed for product group DNS' => ['slug' => ProductGroupType::DNS];
        yield 'Transfer will succeed for product group REDIRECT' => ['slug' => ProductGroupType::REDIRECT];
        yield 'Transfer will succeed for product group RESELLER_HOSTING' => [
            'slug' => ProductGroupType::RESELLER_HOSTING,
        ];
        yield 'Transfer will succeed for product group CLOUDSTACK_VIRTUAL_MACHINE' => [
            'slug' => ProductGroupType::CLOUDSTACK_VIRTUAL_MACHINE,
        ];
        yield 'Transfer will succeed for product group CLOUDSTACK_MANAGER_DOMAIN' => [
            'slug' => ProductGroupType::CLOUDSTACK_MANAGER_DOMAIN,
        ];
        yield 'Transfer will succeed for product group CLOUDSTACK_VOLUME' => [
            'slug' => ProductGroupType::CLOUDSTACK_VOLUME,
        ];
        yield 'Transfer will succeed for product group CLOUDSTACK_OS' => ['slug' => ProductGroupType::CLOUDSTACK_OS];
        yield 'Transfer will succeed for product group ADD_ON' => ['slug' => ProductGroupType::ADD_ON];
        yield 'Transfer will succeed for product group VPS' => ['slug' => ProductGroupType::VPS];
        yield 'Transfer will succeed for product group OTHER' => ['slug' => ProductGroupType::OTHER];
        yield 'Transfer will succeed for product group BACKUP' => ['slug' => ProductGroupType::BACKUP];
    }

    #[Test]
    #[DataProvider('transferAdministrativelyOnlySupported')]
    public function transferAdministrativelySupportedWillComplete(ProductGroupType $slug): void
    {
        Queue::fake();
        $productGroup = new ProductGroupFactory()->createOne(['slug' => $slug]);
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);
        $subscription = new SubscriptionFactory()->for($product)->createOne(['customer_id' => $this->from->id]);

        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription]);

        // check before
        $this->transfer->subscriptions->each(
            function (Subscription $subscription) {
                self::assertNull($subscription->pivot->failed_at);
                self::assertNull($subscription->pivot->reason_failed);
                self::assertNull($subscription->pivot->executed_at);
                self::assertSame($subscription->customer_id, $this->from->id);
            },
        );
        self::assertNull($this->transfer->completed_at);

        $this->executeTransfers->execute($this->transfer->refresh());

        // check after
        $this->transfer->refresh();
        $this->transfer->subscriptions->each(
            function (Subscription $subscription) {
                self::assertNull($subscription->pivot->failed_at);
                self::assertNull($subscription->pivot->reason_failed);
                self::assertNotNull($subscription->pivot->executed_at);
                self::assertSame($subscription->customer_id, $this->receiver->id);
            },
        );
        self::assertNotNull($this->transfer->completed_at);
        Queue::assertPushed(SendEmail::class);
    }

    /**
     * @see ExecuteTransferServiceTest::transferAdministrativelyNotSupportedWillResultInException()
     *
     * @return iterable<string,mixed>
     */
    public static function transferAdministrativelyNotSupported(): iterable
    {
        yield 'Transfer will fail for product group DOMAIN_EXPANSION' => ['slug' => ProductGroupType::DOMAIN_EXPANSION];
        yield 'Transfer will fail for product group RESELLER_DISCOUNT' => [
            'slug' => ProductGroupType::RESELLER_DISCOUNT,
        ];
        yield 'Transfer will fail for product group MICROSOFT_365' => ['slug' => ProductGroupType::MICROSOFT_365];
        yield 'Transfer will fail for product group MANUAL_SUBSCRIPTION' => [
            'slug' => ProductGroupType::MANUAL_SUBSCRIPTION,
        ];
        yield 'Transfer will fail for product group ONE_TIME_SERVICE' => ['slug' => ProductGroupType::ONE_TIME_SERVICE];
        yield 'Transfer will fail for product group VOLUME_DISCOUNT' => ['slug' => ProductGroupType::VOLUME_DISCOUNT];
    }

    #[Test]
    #[DataProvider('transferAdministrativelyNotSupported')]
    public function transferAdministrativelyNotSupportedWillResultInException(ProductGroupType $slug): void
    {
        Queue::fake();
        $productGroup = new ProductGroupFactory()->createOne(['slug' => $slug]);
        $product = new ProductFactory()->for($productGroup)->createOne(['slug' => 'extension_nl']);
        $subscription = new SubscriptionFactory()->for($product)->createOne(['customer_id' => $this->from->id]);

        $this->expectException(TransferException::class);
        $this->expectExceptionMessageIs(
            sprintf(
                'Transfer is not supported for product group %s',
                $subscription->product->productGroup->slug->value,
            ),
        );
        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription]);
        $this->executeTransfers->execute($this->transfer->refresh());
        $this->transfer->refresh();
        $this->transfer->subscriptions->each(
            function (Subscription $subscription) {
                self::assertNotNull($subscription->pivot->failed_at);
                self::assertNotNull($subscription->pivot->reason_failed);
            },
        );
        self::assertNotNull($this->transfer->completed_at);
        Queue::assertPushed(SendEmail::class);
    }

    #[Test]
    public function transferForProductGroupExtensionWillComplete(): void
    {
        $extensionGroup = new ProductGroupFactory()->createOne(['slug' => ProductGroupType::EXTENSION]);
        $product = new ProductFactory()->for($extensionGroup)->createOne(['slug' => 'extension_nl']);
        $subscription = new SubscriptionFactory()->for($product)->createOne(['customer_id' => $this->from->id]);
        $subscription2 = new SubscriptionFactory()->for($product)->createOne(['customer_id' => $this->from->id]);

        $domainDeployment = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $subscription->uuid]);
        $domainDeployment2 = new DomainDeploymentFactory()
            ->withRtrProvider()
            ->createOne(['subscription_uuid' => $subscription2->uuid]);

        $contact = new DomainContactFactory()->createOne([
            'customer_id' => $this->from->id,
            'default_owner' => true,
            'email' => $this->from->email,
        ]);
        $contact2 = new DomainContactFactory()->createOne([
            'customer_id' => $this->receiver->id,
            'default_owner' => true,
            'email' => $this->receiver->email,
        ]);

        $contact->contactOwnerDomainSubscriptions()->save($domainDeployment);
        $contact->contactOwnerDomainSubscriptions()->save($domainDeployment2);

        Queue::fake();

        $this->transfer->accept();
        $this->transfer->subscriptions()->saveMany([$subscription, $subscription2]);

        // check before
        $this->transfer->subscriptions->each(
            function (Subscription $subscription) use ($contact) {
                $contact->refresh();
                self::assertNull($subscription->pivot->failed_at);
                self::assertNull($subscription->pivot->reason_failed);
                self::assertNull($subscription->pivot->executed_at);
                self::assertSame($subscription->customer_id, $this->from->id);

                self::assertNotNull($subscription->domainDeployment);
                $contactOwner = $subscription->domainDeployment->contactOwner;
                self::assertInstanceOf(DomainContact::class, $contactOwner);
                self::assertSame(
                    $contact->toArray(),
                    $contactOwner->toArray(),
                );
            },
        );
        self::assertNull($this->transfer->completed_at);

        $this->executeTransfers->execute($this->transfer->refresh());

        // check after
        $this->transfer->refresh();
        $this->transfer->subscriptions->each(
            function (Subscription $subscription) use ($contact2) {
                $subscription->refresh();
                $contact2->refresh();
                self::assertNull($subscription->pivot->failed_at);
                self::assertNull($subscription->pivot->reason_failed);
                self::assertNotNull($subscription->pivot->executed_at);
                self::assertSame($subscription->customer_id, $this->receiver->id);

                self::assertNotNull($subscription->domainDeployment);
                $subscription->domainDeployment->refresh();
                $contactOwner = $subscription->domainDeployment->contactOwner;
                self::assertInstanceOf(DomainContact::class, $contactOwner);
                self::assertSame(
                    $contact2->toArray(),
                    $contactOwner->toArray(),
                );
            },
        );
        self::assertNotNull($this->transfer->completed_at);
        Queue::assertPushed(SendEmail::class);
    }

    private function createEmailTemplates(): void
    {
        new TemplateFactory()->createMany([
            [
                'slug' => MailTransferAcceptedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferAcceptedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferStartedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferStartedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCompletedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCompletedReceiver::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCreatedSender::getTemplateSlug(),
            ],
            [
                'slug' => MailTransferCreatedReceiver::getTemplateSlug(),
            ],
        ]);
    }
}
