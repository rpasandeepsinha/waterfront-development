<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Listeners;

use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\Enum\InvoiceLineCreditReason;
use Tests\Factories\CustomerFactory;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Harbor\Listeners\SendCreditInvoiceListener;
use Waterfront\Domain\Harbor\Services\HarborApiClient\HarborApi;
use Waterfront\Domain\Harbor\Services\Invoice\InvoiceSingleCrediter;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Events\SubscriptionChangedEvent;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(SendCreditInvoiceListener::class)]
class SendCreditInvoiceListenerTest extends IntegrationTestCase
{
    private Customer $customer;

    private Subscription $subscription;

    private InvoiceRepository $invoiceRepository;

    private InvoiceSingleCrediter $invoiceSingleCrediter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceRepository = self::resolve(InvoiceRepository::class);
        $this->invoiceSingleCrediter = self::resolve(InvoiceSingleCrediter::class);

        $this->customer = new CustomerFactory()->withAddress()->createOne();

        $hostingProductGroup = new ProductGroupFactory()->createOne([
            'name' => ProductGroupType::HOSTING,
            'slug' => ProductGroupType::HOSTING,
        ]);

        $product = new ProductFactory()->createOne([
            'product_group_id' => $hostingProductGroup->id,
            'name' => 'Hosting product',
        ]);

        $this->subscription = new SubscriptionFactory()->for($this->customer)->createOne([
            'product_uuid' => $product->uuid,
        ]);
    }

    #[Test]
    public function sendCreditInvoiceNothingToSendChangeTypeMismatch(): void
    {
        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::UPGRADE,
        );

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );

        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $logMessage = sprintf(
            'Subscription with id : %d (uuid : %s) has been modified but does not need to be credited as it is not a downgrade',
            $this->subscription->id,
            $this->subscription->uuid,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $listener->handle($event);
    }

    #[Test]
    public function sendCreditInvoiceFailMissingDowngradeInvoiceLine(): void
    {
        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::DOWNGRADE,
        );

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );
        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $logMessage = sprintf(
            'Subscription with id : %d (uuid : %s) has no invoice line for the new product : %s',
            $this->subscription->id,
            $this->subscription->uuid,
            sprintf('%s (%s)', $this->subscription->product->name, ProductChangeType::DOWNGRADE->value),
        );

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $listener->handle($event);
    }

    #[Test]
    public function sendCreditInvoiceFailMissingInvoiceLineForCredit(): void
    {
        $translator = self::resolve(TranslatorInterface::class);
        $appendable = $this->subscription->domain !== null
            ? $translator->translate('invoice.description.for') . " {$this->subscription->domain}"
            : '';
        $description = sprintf(
            '%s (%s) %s',
            $this->subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );
        //This is the invoice off the original subscription
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'description' => $description,
            ]);

        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::DOWNGRADE,
        );

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );

        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $logMessage = sprintf(
            'Subscription with id : %d (uuid : %s) has no invoice line that has paid, so there is nothing to credit',
            $this->subscription->id,
            $this->subscription->uuid,
        );

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $listener->handle($event);
    }

    #[Test]
    public function sendCreditInvoiceSuccess(): void
    {
        CarbonImmutable::setTestNow(new CarbonImmutable());

        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'net_price' => 120,
                'paid' => true,
                'sent_to_harbor_at' => CarbonImmutable::yesterday(),
            ]);

        $translator = self::resolve(TranslatorInterface::class);
        $appendable = $this->subscription->domain !== null
            ? $translator->translate('invoice.description.for') . " {$this->subscription->domain}"
            : '';
        $description = sprintf(
            '%s (%s) %s',
            $this->subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );
        //This is the invoice for the new product subscription
        $newInvoiceLine = new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'description' => $description,
                'net_price' => 60,
            ]);

        $expectedCreditInvoiceId = $newInvoiceLine->id + 1;

        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::DOWNGRADE,
        );

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                200,
                [],
                '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );
        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $logMessage2 = sprintf(
            'CreditInvoice for subscription with id : %d (uuid : %s) was successfully send to harbor -> creditInvoiceId : %d ',
            $this->subscription->id,
            $this->subscription->uuid,
            $expectedCreditInvoiceId,
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage2));

        $listener->handle($event);

        self::assertDatabaseHas('invoices', [
            'id' => $expectedCreditInvoiceId,
            'subscription_id' => $this->subscription->id,
            'customer_id' => $this->customer->id,
            'sent_to_harbor_at' => CarbonImmutable::now()->toDateTimeString(),
            'credit_reason' => InvoiceLineCreditReason::REASON_DOWNGRADE,
        ]);

        self::assertDatabaseHas('invoices', [
            'id' => $newInvoiceLine->id,
            'subscription_id' => $this->subscription->id,
            'customer_id' => $this->customer->id,
            'sent_to_harbor_at' => CarbonImmutable::now()->toDateTimeString(),
        ]);
    }

    #[Test]
    public function sendCreditInvoiceFailAuthentication(): void
    {
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'net_price' => 120,
                'paid' => true,
                'sent_to_harbor_at' => CarbonImmutable::yesterday(),
            ]);

        $translator = self::resolve(TranslatorInterface::class);
        $appendable = $this->subscription->domain !== null
            ? $translator->translate('invoice.description.for') . " {$this->subscription->domain}"
            : '';
        $description = sprintf(
            '%s (%s) %s',
            $this->subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );

        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'description' => $description,
                'net_price' => 60,
            ]);

        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::DOWNGRADE,
        );

        $expectedErrorMessage = 'This is a test message!';

        $container = [];
        $history = Middleware::history($container);

        $body = json_encode([
            'message' => $expectedErrorMessage,
        ]);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                401,
                [],
                $body !== false ? $body : '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        Log::shouldReceive('info')->times(2);

        $apiExceptionMessagePrefix = 'The API responded with a error =>  Statuscode : 401 | ';
        $logMessage = sprintf(
            'CreditInvoice for subscription with id : %d (uuid : %s) could not send to Harbor due an api error : %s',
            $this->subscription->id,
            $this->subscription->uuid,
            $apiExceptionMessagePrefix,
        );

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );
        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $listener->handle($event);
    }

    #[Test]
    public function sendCreditInvoiceFailApiError(): void
    {
        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'net_price' => 120,
                'paid' => true,
                'sent_to_harbor_at' => CarbonImmutable::yesterday(),
            ]);

        $translator = self::resolve(TranslatorInterface::class);
        $appendable = $this->subscription->domain !== null
            ? $translator->translate('invoice.description.for') . " {$this->subscription->domain}"
            : '';
        $description = sprintf(
            '%s (%s) %s',
            $this->subscription->product->name,
            ProductChangeType::DOWNGRADE->value,
            $appendable,
        );

        new InvoiceFactory()
            ->for($this->customer)
            ->for($this->subscription)
            ->for($this->subscription->product)
            ->createOne([
                'description' => $description,
                'net_price' => 120,
            ]);

        $event = new SubscriptionChangedEvent(
            subscription: $this->subscription,
            charge: 100,
            changeType: ProductChangeType::DOWNGRADE,
        );

        $container = [];
        $history = Middleware::history($container);

        $body = json_encode([
            'message' => 'This is a test message: harbor fails to handle our request',
        ]);

        $stack = HandlerStack::create(new MockHandler([
            new GuzzleResponse(
                400,
                [],
                $body !== false ? $body : '',
            ),
        ]));
        $stack->push($history);
        $client = new Client(['handler' => $stack]);

        Log::shouldReceive('info')->times(2);

        $apiExceptionMessagePrefix = 'The API responded with a error =>  Statuscode : 400 | ';
        $logMessage = sprintf(
            'CreditInvoice for subscription with id : %d (uuid : %s) could not send to Harbor due an api error : %s',
            $this->subscription->id,
            $this->subscription->uuid,
            $apiExceptionMessagePrefix,
        );

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message): bool => str_contains($message, $logMessage));

        $harborApi = new HarborApi(
            $client,
            self::resolve(ConfigurationInterface::class),
            self::resolve(LoggerInterface::class),
        );
        $listener = new SendCreditInvoiceListener(
            invoiceRepository: $this->invoiceRepository,
            invoiceSingleCrediter: $this->invoiceSingleCrediter,
            harborApi: $harborApi,
        );

        $listener->handle($event);
    }
}
