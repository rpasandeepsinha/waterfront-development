<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Services;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Exceptions\VatNumberValidateFailedException;
use SandwaveIo\Vat\Vat;
use SandwaveIo\Vat\VatNumbers\ValidatesVatNumbers;
use SandwaveIo\Vat\VatRates\ResolvesVatRates;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Config\ApplicationConfig;

#[CoversClass(CustomerVatService::class)]
class CustomerVatServiceTest extends IntegrationTestCase
{
    private const string COUNTRY_CODE_NETHERLANDS = 'NL';
    private const string COUNTRY_CODE_GERMANY = 'DE';
    private const string COUNTRY_CODE_BELGIUM = 'BE';

    // See app/Providers/VatApiFakers/VatNumberApiFaker.php for the correct country code combination.
    private const string VAT_NUMBER_VALID = '861350480B01';
    private const string VAT_NUMBER_INVALID = 'test123';

    private CustomerVatService $vatService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vatService = self::resolve(CustomerVatService::class);
    }

    #[Test]
    public function getCustomerVatDataWithPrivateCustomerAndWithoutVat(): void
    {
        $customer = $this->getPrivateCustomer();

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $customer->refresh();

        self::assertFalse($customer->icp);
        self::assertSame(21.0, $customer->vat_rate);
        self::assertSame('NL21', $customerVatDTO->vatCode);
        self::assertSame(21.0, $customerVatDTO->vatRate);
    }

    #[Test]
    public function getCustomerVatDataWithBusinessCustomerAndWithoutVat(): void
    {
        $customer = $this->getBusinessCustomer();

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $customer->refresh();

        self::assertFalse($customer->icp);
        self::assertSame(21.0, $customer->vat_rate);
        self::assertSame('NL21', $customerVatDTO->vatCode);
        self::assertSame(21.0, $customerVatDTO->vatRate);
    }

    #[Test]
    public function getCustomerVatDataWithDutchBusinessCustomerAndVatNumberButWithoutVatRate(): void
    {
        $customer = $this->getBusinessCustomer(['vat_number' => self::VAT_NUMBER_VALID]);

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $customer->refresh();

        self::assertFalse($customer->icp);
        self::assertSame(21.0, $customer->vat_rate);
        self::assertSame('NL21', $customerVatDTO->vatCode);
        self::assertSame(21.0, $customerVatDTO->vatRate);
    }

    #[Test]
    public function getCustomerVatDataWithForeignBusinessCustomerAndVatNumberButWithoutVatRate(): void
    {
        $customer = $this->getBusinessCustomer(
            ['vat_number' => self::VAT_NUMBER_VALID],
            self::COUNTRY_CODE_BELGIUM
        );

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $customer->refresh();

        self::assertTrue($customer->icp);
        self::assertSame(0.0, $customer->vat_rate);
        self::assertSame('ICP0', $customerVatDTO->vatCode);
        self::assertSame(0.0, $customerVatDTO->vatRate);
    }

    #[Test]
    public function updateCustomerVatUsesCountryVatRateOnInvalidVatNumber(): void
    {
        $customer = $this->getBusinessCustomer(
            ['vat_number' => self::VAT_NUMBER_INVALID],
            self::COUNTRY_CODE_GERMANY,
        );

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        $customer->refresh();

        self::assertFalse($customer->icp);
        self::assertSame(19.0, $customer->vat_rate);
        self::assertSame('DE19', $customerVatDTO->vatCode);
        self::assertSame(19.0, $customerVatDTO->vatRate);
    }

    #[Test]
    public function cachedVatRate(): void
    {
        $expectedVatRate = 21.0;
        $customer = $this->getPrivateCustomer();

        Cache::shouldReceive('has')
            ->once()
            ->with('vat.rate.NL')
            ->andReturnTrue();

        Cache::shouldReceive('get')
            ->once()
            ->with('vat.rate.NL')
            ->andReturn($expectedVatRate);

        $customerVatDTO = $this->vatService->getCustomerVatData($customer);

        self::assertSame($expectedVatRate, $customerVatDTO->vatRate);
    }

    #[Test]
    public function vatApiConnectionFailure(): void
    {
        $customer = $this->getPrivateCustomer(['vat_number' => self::VAT_NUMBER_INVALID]);

        // Clear cache because creating the customer in getPrivateCustomer() caches the VAT number validation.
        Cache::forget('vat.number.validation.' . strtoupper(self::VAT_NUMBER_INVALID));

        $vatNumbers = self::createMock(ValidatesVatNumbers::class);
        $vatNumbers->expects(self::once())->method('verifyVatNumber')->willThrowException(
            (new VatNumberValidateFailedException('test', [], 500))
        );

        $vatRates = self::createMock(ResolvesVatRates::class);
        $vatRates->expects(self::never())->method('getDefaultVatRateForCountry');

        $vat = new Vat(vatRateResolver: $vatRates, vatNumberVerifier: $vatNumbers);
        $vatService = new CustomerVatService(
            $vat,
            self::createStub(ConfigurationInterface::class),
            self::createStub(ApplicationConfig::class),
            self::resolve(LoggerInterface::class),
        );

        $this->app->bind(Vat::class, fn (): Vat => $vat);

        $vatService->updateCustomerVat($customer);
        $customer->refresh();

        self::assertFalse($customer->icp);
    }

    /**
     * @param array<string, mixed> $customerData
     */
    private function getCustomer(
        array $customerData = [],
        string $countryCode = self::COUNTRY_CODE_NETHERLANDS
    ): Customer {
        return new CustomerFactory()
            ->withAddress([
                'country_code' => $countryCode,
            ])
            ->createOne([
                ...[
                    'vat_rate' => null,
                ],
                ...$customerData,
            ]);
    }

    /**
     * @param array<string, mixed> $customerData
     */
    private function getPrivateCustomer(
        array $customerData = [],
        string $countryCode = self::COUNTRY_CODE_NETHERLANDS
    ): Customer {
        return $this->getCustomer(
            ['organization' => null, 'department' => null, ...$customerData],
            $countryCode,
        );
    }

    /**
     * @param array<string, mixed> $customerData
     */
    private function getBusinessCustomer(
        array $customerData = [],
        string $countryCode = self::COUNTRY_CODE_NETHERLANDS,
    ): Customer {
        return $this->getCustomer(
            ['organization' => 'sandwave.io', 'department' => 'testing', ...$customerData],
            $countryCode,
        );
    }
}
