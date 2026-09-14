<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Hubspot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\DataProvider\DomainSubscriptionDataProvider;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\HubspotController;

#[CoversClass(HubspotController::class)]
class HubspotControllerTest extends IntegrationTestCase
{
    #[Test]
    public function getCustomerDataReturnsData(): void
    {
        $subscription = DomainSubscriptionDataProvider::subscription();

        $this->actingAsSystem()
            ->getJson($this->generateRoute('webhooks.hubspot.get-customer-data', ['sw_uuid' => $subscription->uuid]))
            ->assertOk()
            ->assertJsonFragment(['uuid' => $subscription->customer->uuid->toString()]);
    }

    #[Test]
    public function getCustomerDataReturns404(): void
    {
        $this->actingAsSystem()
            ->getJson($this->generateRoute('webhooks.hubspot.get-customer-data', [
                'sw_uuid' => Uuid::uuid4()->toString(),
            ]))
            ->assertNotFound();
    }
}
