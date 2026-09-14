<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Microsoft365;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use SandwaveIo\Office365\Response\CustomerAgreementAttestationResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\Microsoft365Controller;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerAgreementException;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(Microsoft365Controller::class)]
class GetMicrosoft365CustomerAgreementTest extends IntegrationTestCase
{
    private Customer $customer;

    private MockObject&Microsoft365Service $microsoft365Service;

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
    public function getMicrosoft365CustomerAgreement(): void
    {
        $customerAgreementAttestationResponse = new CustomerAgreementAttestationResponse(
            attestationId: '1',
            attestationUrl: 'https://cdn.partner.microsoft.com/mca/?attestationid=49a69a39-8244-4c51-9805-4f5aa3adac6b',
            attestationStatus: 'unverified',
        );

        $this->microsoft365Service
            ->expects(self::once())
            ->method('getMicrosoftCustomerAgreementUrl')
            ->with($this->customer)
            ->willReturn($customerAgreementAttestationResponse);

        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.microsoft365.microsoft-customer-agreement'),
        );
        $response->assertOk();

        /** @var object{mcaUrl: string} $data */
        $data = json_decode((string) $response->getContent());
        self::assertSame($customerAgreementAttestationResponse->attestationUrl, $data->mcaUrl);
    }

    #[Test]
    public function getMicrosoft365CustomerAgreementReturnsServiceUnavailableOnException(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getMicrosoftCustomerAgreementUrl')
            ->with($this->customer)
            ->willThrowException(
                new MicrosoftCustomerAgreementException('Could not retrieve microsoft customer agreement url'),
            );

        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.microsoft365.microsoft-customer-agreement'),
        );

        $response->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->assertJsonStructure(['message', 'errors']);
    }

    #[Test]
    public function getMicrosoft365CustomerAgreementReturnsServiceUnavailableWhenCustomerIsNotReady(): void
    {
        $this->microsoft365Service
            ->expects(self::once())
            ->method('getMicrosoftCustomerAgreementUrl')
            ->with($this->customer)
            ->willThrowException(new MicrosoftCustomerNotFoundException('Microsoft Customer has not yet been created'));

        $response = $this->actingAsCustomer($this->customer)->getJson(
            $this->generateRoute('partners.microsoft365.microsoft-customer-agreement'),
        );

        $response->assertStatus(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->assertExactJson([
            'message' => 'microsoft365.error.customer-not-ready',
            'errors' => [],
        ]);
    }
}
