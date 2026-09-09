<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Customers\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Customers\Actions\NovaAnonymizeCustomerAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;

#[CoversClass(NovaAnonymizeCustomerAction::class)]
class AnonymizeCustomerTest extends IntegrationTestCase
{
    private Customer $customer;

    private NovaAnonymizeCustomerAction $anonymizeCustomer;

    public function setUp(): void
    {
        parent::setUp();

        // Disable LH interaction
        $this->app->bind(LighthouseApiService::class, fn () => self::createStub(LighthouseApiService::class));

        $this->anonymizeCustomer = self::resolve(NovaAnonymizeCustomerAction::class);

        $this->customer = new CustomerFactory()->createOne();
    }

    #[Test]
    public function anonymizeCustomerAction(): void
    {
        $response = $this->anonymizeCustomer->handle(
            new ActionFields(new Collection(['confirm_check' => true]), new Collection()),
            new Collection([$this->customer]),
        );

        $this->customer->refresh();

        $customerNumber = $this->customer->customer_number;

        self::assertSame("anonymized-first_name-$customerNumber", $this->customer->first_name);
        self::assertSame("anonymized-last_name-$customerNumber", $this->customer->last_name);
        self::assertSame("anonymized.customer.$customerNumber@sandwave.io", $this->customer->email);

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.anonymize_customer.successful', $message->text);
        self::assertNotNull($this->customer->anonymized_at);
    }

    #[Test]
    public function anonymizeCustomerActionFailed(): void
    {
        $response = $this->anonymizeCustomer->handle(
            new ActionFields(new Collection([]), new Collection()),
            new Collection([$this->customer]),
        );

        $this->customer->refresh();
        self::assertNull($this->customer->anonymized_at);

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['danger'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.anonymize-customer.error.did-not-confirm', $message->text);
    }

    #[Test]
    public function anonymizeCustomerActionAlreadyAnonymizedFailed(): void
    {
        $this->anonymizeCustomer->handle(
            new ActionFields(new Collection(['confirm_check' => true]), new Collection()),
            new Collection([$this->customer]),
        );

        $this->customer->refresh();

        self::assertNotNull($this->customer->anonymized_at);

        $response = $this->anonymizeCustomer->handle(
            new ActionFields(new Collection(['confirm_check' => true]), new Collection()),
            new Collection([$this->customer]),
        );

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['danger'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.anonymize-customer.error.already-anonymized', $message->text);
    }
}
