<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Customers;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use SandwaveIo\Vat\Vat;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Waterfront\Controllers\CustomersController;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Mailer\CustomerEmailUpdateEmail;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Providers\VatApiFakers\VatNumberApiFaker;
use Waterfront\Support\Providers\VatApiFakers\VatRateApiFaker;

#[CoversClass(CustomersController::class)]
class CustomerEditTest extends IntegrationTestCase
{
    private Customer $customer;

    private Vat $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vat = new Vat(
            null,
            $this->app->make(VatRateApiFaker::class),
            $this->app->make(VatNumberApiFaker::class),
        );
        $this->customer = new CustomerFactory()->withAddress()->createOne();
    }

    #[Test]
    public function editCustomer(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'notify@sandwave.io',
                    'purchase_reference' => 'test',
                    'department' => 'development',
                    'organization' => 'Sandwave.io',
                ]),
            )
            ->assertExactJson(['message' => self::resolve(TranslatorInterface::class)->translate('status.success')]);

        self::assertDatabaseHas('customer_addresses', ['city' => 'Night City']);
        self::assertDatabaseHas('customers', [
            'purchase_reference' => 'test',
            'department' => 'development',
            'organization' => 'Sandwave.io',
        ]);
    }

    #[Test]
    public function editCustomerMissingEmail(): void
    {
        $customer = $this->prepareCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'email' => [self::resolve(TranslatorInterface::class)->translate('validation.required')],
                ],
                'message' => self::resolve(TranslatorInterface::class)->translate('validation.required'),
            ]);
    }

    #[Test]
    public function editCustomerInvalidEmail(): void
    {
        $customer = $this->prepareCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'invalid email@change.nl',
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'email' => [self::resolve(TranslatorInterface::class)->translate('validation.email')],
                ],
                'message' => self::resolve(TranslatorInterface::class)->translate('validation.email'),
            ]);
    }

    #[Test]
    public function editCustomerNoAddressCoupledButProvided(): void
    {
        $customer = $this->prepareCustomer($this->customer);
        $fullLoad = $customer->toArray();

        $customerAddress = $customer->address;

        self::assertInstanceOf(CustomerAddress::class, $customerAddress);

        $customerAddress->delete();

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($fullLoad, [
                    'address' => [$fullLoad['address']],
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertServerError();
    }

    #[Test]
    public function editCustomerWithVatNumber(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'vat_number' => 'NL861350480B01',
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk()
            ->assertExactJson(['message' => self::resolve(TranslatorInterface::class)->translate('status.success')]);
    }

    /**
     * This test is for a business account located in The Netherlands.
     *DomainPlaceholderServiceTest
     * Vat rate should be 21%
     * ICP TRUE.
     */
    #[Test]
    public function editCustomerFromTheNetherlandsBusinessAccountWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/notify@sandwave.io' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareDutchCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'vat_number' => 'NL861350480B01',
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        $customer = $customer->refresh();
        $vatRateFromApi = $this->vat->europeanVatRate($customer->address->country_code ?? 'NL');

        self::assertSame(
            $customer->vat_rate,
            $vatRateFromApi,
            'Vat rate is not correct. Should be 21.0 got: ' . $customer->vat_rate,
        );
        self::assertSame(
            21.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 21.0 got: ' . $customer->vat_rate,
        );
        self::assertFalse($customer->icp, 'ICP for dutch business account is true, should be false.');
    }

    /**
     * This test is for a business account located in Europe.
     *
     * Vat rate should be 0%
     * ICP TRUE
     */
    #[Test]
    public function editCustomerFromGermanyBusinessAccountWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareGermanCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'vat_number' => 'DE861350480B01',
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        $customer = $customer->refresh();

        self::assertSame(
            0.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 0.0 got: ' . $customer->vat_rate,
        );
        self::assertTrue($customer->icp, 'ICP for German business account is false, should be true.');
    }

    /**
     * This test is for a business account located outside Europe.
     *
     * Vat rate should 0%
     * ICP FALSE
     */
    #[Test]
    public function editCustomerFromMexicoBusinessAccountWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareMexicanCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'vat_number' => 'MX861350480B01',
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        // Mexico is not part of the EU, so we expect a 0% vat rate.
        $vatRateFromApi = $this->vat->europeanVatRate($customer->address->country_code ?? 'NL', null, 0.0);

        $customer = $customer->refresh();
        self::assertSame(
            $customer->vat_rate,
            $vatRateFromApi,
            'Vat rate is not correct. Should be 0.0 got: ' . $customer->vat_rate,
        );
        self::assertSame(
            0.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 0.0 got: ' . $customer->vat_rate,
        );
        self::assertFalse($customer->icp, 'ICP for Mexican business account is true, should be false.');
    }

    /**
     * This test is for a 'regular' account located in The Netherlands.
     *
     * Vat rate should be 21%
     * ICP FALSE
     */
    #[Test]
    public function editCustomerFromTheNetherlandsWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareDutchCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        $customer = $customer->refresh();

        $vatRateFromApi = $this->vat->europeanVatRate($customer->address->country_code ?? 'NL');

        self::assertSame(
            $customer->vat_rate,
            $vatRateFromApi,
            'Vat rate is not correct. Should be 21.0 got: ' . $customer->vat_rate,
        );
        self::assertSame(
            21.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 21.0 got: ' . $customer->vat_rate,
        );
        self::assertFalse($customer->icp, 'ICP for regular customer is true, should be false.');
    }

    /**
     * This test is for a 'regular' account located in Europe.
     *
     * Vat rate should be 19%
     * ICP FALSE
     */
    #[Test]
    public function editCustomerFromGermanyWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareGermanCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        $customer = $customer->refresh();
        $vatRateFromApi = $this->vat->europeanVatRate($customer->address->country_code ?? 'NL');

        self::assertSame(
            $customer->vat_rate,
            $vatRateFromApi,
            'Vat rate is not correct. Should be 19.0 got: ' . $customer->vat_rate,
        );
        self::assertSame(
            19.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 19.0 got: ' . $customer->vat_rate,
        );
        self::assertFalse($customer->icp, 'ICP for regular customer is true, should be false.');
    }

    /**
     * This test is for a 'regular' account located outside Europe.
     *
     * Vat rate 0%
     * ICP FALSE
     */
    #[Test]
    public function editCustomerFromMexicoWithVatRateChange(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customer = $this->prepareMexicanCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertOk();

        $customer = $customer->refresh();

        $vatRateFromApi = $this->vat->europeanVatRate($customer->address->country_code ?? 'NL', null, 0.0);

        self::assertSame(
            $customer->vat_rate,
            $vatRateFromApi,
            'Vat rate is not correct. Should be 0.0 got: ' . $customer->vat_rate,
        );
        self::assertSame(
            0.0,
            $customer->vat_rate,
            'Vat rate is not correct. Should be 0.0 got: ' . $customer->vat_rate,
        );
        self::assertFalse($customer->icp, 'ICP for regular customer is true, should be false.');
    }

    #[Test]
    public function editCustomerWithSpecialCharactersInOrganisationsReturns422(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@change.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);
        $this->assertEmailsSend([CustomerEmailUpdateEmail::class]);

        $customerData = $this->prepareCustomer($this->customer)->toArray();
        $customerData['address'] = [$customerData['address']];
        $customerData['email'] = 'email@change.nl';

        $this->actingAsCustomer($this->customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customerData['uuid']),
                $customerData,
            )
            ->assertOk()
            ->assertExactJson([
                'message' => self::resolve(TranslatorInterface::class)->translate('status.success'),
            ]);

        $customerData = $this->prepareCustomer($this->customer)->toArray();
        $customerData['organization'] = 'Poggers !@)$*(%Inc';
        $customerData['address'] = [$customerData['address']];
        $customerData['email'] = 'notify@sandwave.io';

        $this->actingAsCustomer($this->customer)
            ->patchJson($this->generateRoute('partners.customers.patch', $customerData['uuid']), $customerData)
            ->assertUnprocessable();
    }

    #[Test]
    public function invalidZipCode(): void
    {
        $customer = $this->prepareInvalidZipCodeCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'email' => $customer->email,
                    'address' => [$customer->toArray()['address']],
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['zip_code' => ['Dit veld dient het volgende formaat te hebben: 1234 AB']]);
    }

    #[Test]
    public function invalidStreetNumber(): void
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);

        $customer = $this->prepareInvalidStreetNumberCustomer($this->customer);

        $this->actingAsCustomer($customer)
            ->patchJson(
                $this->generateRoute('partners.customers.patch', $customer->uuid),
                array_merge($customer->toArray(), [
                    'address' => [$customer->toArray()['address']],
                    'email' => 'notify@sandwave.io',
                ]),
            )
            ->assertUnprocessable()
            ->assertJsonFragment(['street_number' => ['Dit veld is verplicht.']]);
    }

    private function prepareCustomer(Customer $customer): Customer
    {
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/api/login' => Http::response(['data' => [
                'user' => 'test.kees@sandwave.io',
                'token' => 'sandwave',
            ]]),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/email@cange.nl' => function (): never {
                throw new LighthouseException();
            },
        ]);

        unset($customer->email, $customer->locale, $customer->gender);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Nightstreet';
            $address->city = 'Night City';
            $address->zip_code = '2860 DE';
            $address->country_code = 'NL';

            $customer->address = $address;
        }

        $customer->first_name = 'Jason';
        $customer->last_name = 'Yamal';
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '55539669';

        return $customer;
    }

    private function prepareDutchCustomer(Customer $customer): Customer
    {
        unset($customer->email, $customer->locale, $customer->gender);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Kapellestreet';
            $address->city = 'Kapelle city';
            $address->zip_code = '2860 AB';
            $address->country_code = 'NL';

            $customer->address = $address;
        }

        $customer->first_name = 'Jason';
        $customer->last_name = 'Yamal';
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '55539669';

        return $customer;
    }

    private function prepareInvalidStreetNumberCustomer(Customer $customer): Customer
    {
        unset($customer->email, $customer->locale, $customer->gender);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Kapellestreet';
            $address->street_number = '';
            $address->city = 'Kapelle city';
            $address->zip_code = '2860 AB';
            $address->country_code = 'NL';

            $customer->address = $address;
        }

        $customer->first_name = 'Jason';
        $customer->last_name = 'Yamal';
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '55539669';

        return $customer;
    }

    private function prepareInvalidZipCodeCustomer(Customer $customer): Customer
    {
        unset($customer->locale);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Kapellestreet';
            $address->city = 'Kapelle city';
            $address->zip_code = '2860';
            $address->country_code = 'NL';

            $customer->address = $address;
        }

        $customer->first_name = 'Jason';
        $customer->last_name = 'Yamal';
        $customer->phone_country_code = '31';
        $customer->phone_area_code = '6';
        $customer->phone_subscriber_number = '55539669';

        return $customer;
    }

    private function prepareGermanCustomer(Customer $customer): Customer
    {
        unset($customer->email, $customer->locale, $customer->gender);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Münsterstraße';
            $address->city = 'Munich';
            $address->zip_code = '26133';
            $address->country_code = 'DE';

            $customer->address = $address;
        }

        $customer->first_name = 'Albert';
        $customer->last_name = 'Einstein';
        $customer->phone_country_code = '49';
        $customer->phone_area_code = '20';
        $customer->phone_subscriber_number = '1294770';

        return $customer;
    }

    private function prepareMexicanCustomer(Customer $customer): Customer
    {
        unset($customer->email, $customer->locale, $customer->gender);

        $address = $customer->address;

        if ($address instanceof CustomerAddress) {
            $address->street_name = 'Avenida Álvaro Obregón';
            $address->city = 'Mexico Stad';
            $address->zip_code = '02860';
            $address->country_code = 'MX';

            $customer->address = $address;
        }

        $customer->first_name = 'José';
        $customer->last_name = 'Diego';
        $customer->phone_country_code = '52';
        $customer->phone_area_code = '55';
        $customer->phone_subscriber_number = '54279739';

        return $customer;
    }
}
