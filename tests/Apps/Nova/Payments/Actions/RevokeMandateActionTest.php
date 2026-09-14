<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Payments\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Message;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\MandateFactory;
use Tests\Factories\MollieCustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Payments\Actions\NovaRevokeMandateAction;
use Waterfront\Domain\Payments\Managers\MandateRevokeManager;
use Waterfront\Domain\Payments\Models\Mandate;

#[CoversClass(NovaRevokeMandateAction::class)]
class RevokeMandateActionTest extends IntegrationTestCase
{
    /** @var Collection<int, Mandate> */
    private Collection $mandates;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = new CustomerFactory()->createOne();
        $mollieCustomer = new MollieCustomerFactory()->for($customer)->createOne();
        $mandate = new MandateFactory()->for($mollieCustomer)->createOne();

        $this->mandates = new Collection([$mandate]);
    }

    #[Test]
    public function revokeMandateAction(): void
    {
        $this->app->bind(function (): MandateRevokeManager {
            $mock = self::createStub(MandateRevokeManager::class);

            $mock->method('revokeMandate');

            return $mock;
        });

        $action = self::resolve(NovaRevokeMandateAction::class);
        $fields = $this->getActionFields();
        $response = $action->handle($fields, $this->mandates);

        self::assertInstanceOf(ActionResponse::class, $response);
        $message = $response['message'];
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('nova-action.success.revoke_action', $message->text);
    }

    private function getActionFields(): ActionFields
    {
        return new ActionFields(
            new Collection([]),
            new Collection([]),
        );
    }
}
