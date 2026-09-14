<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Microsoft365;

use DateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\Office365\Response\CustomerAgreementResponse;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\Microsoft365Controller;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(Microsoft365Controller::class)]
class Microsoft365CustomerAgreementSignedTest extends IntegrationTestCase
{
    private Customer $customer;

    private Microsoft365Service&MockObject $microsoft365Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();

        new Microsoft365CustomerInfoFactory()->createOne([
            'customer_id' => $this->customer->id,
            'tenant_name' => $this->customer->customer_number . '.onmicrosoft.com',
            'mca_signed_at' => null,
        ]);

        $this->microsoft365Service = self::createMock(Microsoft365Service::class);
        $this->app->bind(Microsoft365Service::class, fn (): Microsoft365Service => $this->microsoft365Service);
    }

    #[Test]
    public function microsoft365CustomerAgreementSigned(): void
    {
        $customerAgreementResponse = new CustomerAgreementResponse(
            mcaSigned: true,
            attestationId: '1',
            attestationUrl: 'https://cdn.partner.microsoft.com/mca/?attestationid=49a69a39-8244-4c51-9805-4f5aa3adac6b',
            attestationStatus: 'accepted',
            dateAgreed: new DateTime(),
        );

        $this->microsoft365Service
            ->expects(self::once())
            ->method('getMicrosoftCustomerAgreement')
            ->with($this->customer)
            ->willReturn($customerAgreementResponse);

        $this->microsoft365Service->expects(self::once())->method('prepareOrders');

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.microsoft365.microsoft-customer-agreement-signed'),
            )
            ->assertNoContent();
    }

    #[Test]
    public function microsoft365CustomerAgreementNotSigned(): void
    {
        $customerAgreementResponse = new CustomerAgreementResponse(
            false,
            attestationId: '1',
            attestationUrl: null,
            attestationStatus: 'pending',
            dateAgreed: null,
        );

        $this->microsoft365Service
            ->expects(self::once())
            ->method('getMicrosoftCustomerAgreement')
            ->with($this->customer)
            ->willReturn($customerAgreementResponse);

        $this->actingAsCustomer($this->customer)
            ->postJson(
                $this->generateRoute('partners.microsoft365.microsoft-customer-agreement-signed'),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'microsoft365.error.mca-not-signed',
            ])
            ->assertJsonFragment([
                'errors' => [
                    'mca' => [
                        'microsoft365.error.mca-not-signed',
                    ],
                ],
            ]);
    }
}
