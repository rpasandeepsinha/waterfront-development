<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Models;

use Illuminate\Support\Facades\Config;
use libphonenumber\NumberParseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(Customer::class)]
class CustomerTest extends IntegrationTestCase
{
    #[Test]
    public function defaultPaymentTermsForNewCustomerAreSameAsConfiguredDefault(): void
    {
        Config::set('constants.payment-terms.default', 55);

        $privateCustomer = new CustomerFactory()->createOne([
            'terms_of_payment' => null,
            'organization' => null,
        ]);
        $businessCustomer = new CustomerFactory()->createOne([
            'terms_of_payment' => null,
            'organization' => 'FooBar Logistics',
        ]);

        self::assertSame(55, $privateCustomer->terms_of_payment);
        self::assertSame(55, $businessCustomer->terms_of_payment);
    }

    /**
     *
     * @context:
     * A customer is a Company customer when organization, vat and coc number are set.
     *
     * @PassWhen:
     * 1. Customer without organization, vat and coc number gives false
     * 2. Customer without vat and coc number gives false
     * 3. Customer without coc number gives false
     * 4. Customer with organization, btw, vat and coc number is set gives true
     */
    #[Test]
    public function isCompanyCustomer(): void
    {
        $customer = new CustomerFactory()->createOne([
            'organization' => null,
            'vat_number' => null,
            'coc_number' => null,
        ]);

        self::assertFalse(
            $customer->isCompany(),
            'Customer without organization, vat and coc number did not give false',
        );

        $customer->organization = 'Sandwave';
        $customer->save();

        self::assertFalse(
            $customer->isCompany(),
            'Customer without vat and coc number did not give false',
        );

        $customer->vat_number = 'NL000099998B99';
        $customer->save();

        self::assertFalse(
            $customer->isCompany(),
            'Customer without organization, coc number did not give false',
        );
        $customer->coc_number = 'NL000099998B99';
        $customer->save();

        self::assertTrue(
            $customer->isCompany(),
            'Customer with organization, vat and coc number did not give back true',
        );
    }

    #[DataProvider('provideCustomersWithInvalidPhoneNumberData')]
    #[Test]
    public function customerHasInvalidPhoneNumber(string $countryCode, string $areaCode, string $subscriberNumber): void
    {
        $customer = new CustomerFactory()->createOne([
            'phone_country_code' => $countryCode,
            'phone_area_code' => $areaCode,
            'phone_subscriber_number' => $subscriberNumber,
        ]);

        self::assertSame('', $customer->phone_number);
    }

    #[Test]
    public function customerHasValidPhoneNumber(): void
    {
        $customer = new CustomerFactory()->createOne([
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '87281426',
        ]);

        self::assertSame('(+31) 6-87281426', $customer->phone_number);
    }

    #[DataProvider('provideInvalidPhoneNumbers')]
    #[Test]
    public function setInvalidPhoneNumber(string $phoneNumber): void
    {
        $customer = new CustomerFactory()->createOne();

        try {
            $customer->phone_number = $phoneNumber;

            self::expectException(NumberParseException::class);
        } catch (NumberParseException $e) {
            self::assertSame(NumberParseException::NOT_A_NUMBER, $e->getCode());
            self::assertSame(
                self::resolve(TranslatorInterface::class)->translate('customer.phone-country-error'),
                $e->getMessage(),
            );
        }
    }

    #[DataProvider('provideValidPhoneNumbers')]
    #[Test]
    public function setValidPhoneNumber(string $phoneNumber): void
    {
        $customer = new CustomerFactory()->createOne();

        $customer->phone_number = $phoneNumber;

        self::assertSame('(+31) 6-87281426', $customer->phone_number);
    }

    #[Test]
    public function setTooShortPhoneNumber(): void
    {
        $customer = new CustomerFactory()->createOne();

        try {
            $customer->phone_number = '123';

            self::expectException(NumberParseException::class);
        } catch (NumberParseException $e) {
            self::assertSame(NumberParseException::NOT_A_NUMBER, $e->getCode());
            self::assertSame(
                self::resolve(TranslatorInterface::class)->translate('customer.phone-country-error'),
                $e->getMessage(),
            );
        }
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideCustomersWithInvalidPhoneNumberData(): iterable
    {
        yield 'Customer with only a (phone) country code' => ['31', '', ''];
        yield 'Customer with only a (phone) area code' => ['', '6', ''];
        yield 'Customer with only a (phone) subscriber number' => ['', '', '87281426'];
        yield 'Customer with only a (phone) country code and area code' => ['31', '6', ''];
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideInvalidPhoneNumbers(): iterable
    {
        yield 'Phone number with non-numeric characters' => ['NON-NUMERIC'];
        yield 'Phone number with insufficient amount of characters' => ['123'];
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function provideValidPhoneNumbers(): iterable
    {
        yield 'Phone number with leading +' => ['+31687281426'];
        yield 'Phone number with leading + and a hyphen' => ['+3168-7281426'];
    }
}
