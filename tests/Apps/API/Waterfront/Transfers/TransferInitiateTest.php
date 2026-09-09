<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Transfers;

use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Factories\TransferFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\TransferController;
use Waterfront\Apps\API\Waterfront\Resources\ProductTransferPresenter;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplate;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedSender;
use Waterfront\Domain\Transfers\Models\Transfer;

#[CoversClass(TransferController::class)]
#[CoversClass(ProductTransferPresenter::class)]
class TransferInitiateTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function store(): void
    {
        self::assertEmailsSend([
            MailTransferCreatedSender::class,
            MailTransferCreatedReceiver::class,
        ]);

        $this->populateTestingSubscriptions($this->customer);

        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);

        $randomCustomer = new CustomerFactory()->createOne();

        $transferPayload['receiver']['email'] = $randomCustomer->email;
        $transferPayload['receiver']['customer_number'] = $randomCustomer->customer_number;

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertCreated();

        self::assertDatabaseHas('transfers', [
            'uuid'             => $response->json('id'),
            'from_customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function storeWithDomainSubscription(): void
    {
        $subscriptions = $this->populateTestingSubscriptions($this->customer);
        $template = DnsCustomerTemplate::create([
            'name' => 'testTemplate',
            'customer_id' => $this->customer->id,
        ]);
        new DomainDeploymentFactory()->for(new ProviderFactory()->domainOpenProvider()->createOne())->createOne([
            'subscription_uuid' => $subscriptions->firstOrFail()->uuid,
            'template_id'       => $template->id,
        ]);

        $subscriptions = $subscriptions->fresh();

        $randomCustomer = new CustomerFactory()->createOne();

        $transferPayload = [];
        $transferPayload['receiver']['email'] = $randomCustomer->email;
        $transferPayload['receiver']['customer_number'] = $randomCustomer->customer_number;
        $transferPayload['subscriptions'] = [['uuid' => $subscriptions->firstOrFail()->uuid]];

        $response = $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();

        self::assertDatabaseMissing('transfers', [
            'uuid'             => $response->json('id'),
            'from_customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function storeNoSubscriptionArray(): void
    {
        $this->populateTestingSubscriptions($this->customer);

        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);
        $transferPayload['receiver']['email'] = $this->customer->email;
        $transferPayload['receiver']['customer_number'] = $this->customer->customer_number;
        unset($transferPayload['subscriptions']);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();
    }

    #[Test]
    public function storeNoReceiverArray(): void
    {
        $this->populateTestingSubscriptions($this->customer);

        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);
        unset($transferPayload['receiver']);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();
    }

    #[Test]
    public function storeValidationFailedInvalidSubscriptionsDifferentCustomer(): void
    {
        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);
        $randomCustomer = new CustomerFactory()->createOne();
        $subscriptions = $this->populateTestingSubscriptions($this->customer);

        $subscription = $subscriptions->firstOrFail();
        $subscription->customer_id = $randomCustomer->id;
        $subscription->save();

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();

        self::assertFalse(Transfer::exists());
    }

    #[Test]
    public function storeValidationFailedInvalidSubscriptionsAlreadyInProgress(): void
    {
        $customerReceiver = new CustomerFactory()->createOne();

        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);
        $transferPayload['receiver']['email'] = $this->customer->email;
        $transferPayload['receiver']['customer_number'] = $this->customer->customer_number;

        $this->populateCoupledTransfer($this->customer, $customerReceiver);

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();

        self::assertDatabaseMissing('transfers', ['to_customer_id' => $this->customer->customer_number]);
    }

    #[Test]
    public function storeValidationFailedInvalidReceiver(): void
    {
        $this->populateTestingSubscriptions($this->customer);

        /** @var array<array<string>> $transferPayload */
        $transferPayload = json_decode((string) file_get_contents(__DIR__ . '/data/transfer.json'), true, 512, JSON_THROW_ON_ERROR);
        $transferPayload['receiver']['email'] = 'myfakeemail@sandwave.io';
        $transferPayload['receiver']['customer_number'] = 676676;

        $this->actingAsCustomer($this->customer)->postJson(
            $this->generateRoute('partners.transfers.initiate'),
            $transferPayload
        )->assertUnprocessable();

        self::assertDatabaseMissing('transfers', ['to_customer_id' => 676676]);
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function populateTestingSubscriptions(Customer $customer): Collection
    {
        $product = new ProductFactory()->for(new ProductGroupFactory()->extension()->createOne())->createOne();
        $product2 = new ProductFactory()->for(new ProductGroupFactory()->hosting()->createOne())->createOne();

        $subscription = new SubscriptionFactory()->for($customer)->for($product)->createOne(['uuid' => 'my-uuid-good-1']);

        $subscription2 = new SubscriptionFactory()->for($customer)->for($product2)->createOne(['uuid' => 'my-uuid-good-2']);

        return new Collection([$subscription, $subscription2]);
    }

    private function populateCoupledTransfer(
        Customer $from,
        Customer $receiver
    ): void {
        /** @var Subscription $subscription */
        $subscription = $this->populateTestingSubscriptions($from)->first();

        $transfer = new TransferFactory()->createOne([
            'from_customer_id' => $from->id,
            'to_customer_id' => $receiver->id,
        ]);

        $subscription->transfers()->save($transfer);
    }
}
