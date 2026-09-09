<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Payments\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\ActionRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Payments\Actions\NovaCreateDirectDebitMandateAction;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Actions\CreateDirectDebitMandateAction;
use Waterfront\Domain\Payments\Jobs\RequestDirectDebitMandateJob;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

#[CoversClass(CreateDirectDebitMandateAction::class)]
class CreateDirectDebitMandateActionTest extends IntegrationTestCase
{
    private Customer $customer;

    private CarbonImmutable $baseTime;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
        $this->baseTime = CarbonImmutable::createFromTimeString('2023-08-11T12:34:03.000+02:00');
    }

    #[Test]
    public function validRequestDispatchedDirectDebitJob(): void
    {
        $this->app->bind(function (): CreateDirectDebitMandateAction {
            $mock = self::createMock(CreateDirectDebitMandateAction::class);

            $mollieCustomer = new MollieCustomer();
            $mollieCustomer->id = 5;

            $mandate = new Mandate();
            $mandate->id = 10;
            $mandate->payt_mandate_reference_id = '5';
            $mandate->signature_date = $this->baseTime;
            $mandate->method = MollieMandateMethod::DIRECTDEBIT;
            $mandate->mollieCustomer = $mollieCustomer;

            $mock->method('execute')
                ->willReturn($mandate);

            return $mock;
        });

        Queue::fake();
        $action = self::resolve(NovaCreateDirectDebitMandateAction::class);
        [$actionRequest, $actionFields] = $this->getActionRequestAndFields([
            'signature_date' => $this->baseTime->toDateString(),
            'consumer_name' => 'Test consumer',
            'consumer_account' => 'NL18RABO0123459876',
        ]);
        $action->validateFields($actionRequest);
        $response = $action->handle($actionFields, new Collection([$this->customer]));

        Queue::assertPushed(RequestDirectDebitMandateJob::class);

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.create_direct_debit_mandate.async', $message->text);
    }

    #[Test]
    public function invalidRequestDoesntDispatchJob(): void
    {
        self::expectException(ValidationException::class);
        self::expectExceptionMessageIs('Dit veld is geen valide IBAN.');
        Queue::fake();

        $action = self::resolve(NovaCreateDirectDebitMandateAction::class);
        [$actionRequest] = $this->getActionRequestAndFields([
            'signature_date' => $this->baseTime->toDateString(),
            'consumer_name' => 'Test consumer',
            'consumer_account' => 'account',
        ]);
        $action->validateFields($actionRequest);

        Queue::assertNotPushed(RequestDirectDebitMandateJob::class);
    }

    /**
     * @param array<string, string> $payload
     *
     * @return array{0: ActionRequest, 1: ActionFields}
     */
    private function getActionRequestAndFields(array $payload): array
    {
        return [
            new ActionRequest($payload),
            new ActionFields(new Collection($payload), new Collection([])),
        ];
    }
}
