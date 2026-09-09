<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Action;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Actions\StoreCustomerAction;
use Waterfront\Domain\Customers\DTO\PhoneDTO;
use Waterfront\Domain\Customers\Enums\Gender;
use Waterfront\Domain\Customers\Enums\PaymentType;

#[CoversClass(StoreCustomerAction::class)]
class StoreCustomerActionTest extends IntegrationTestCase
{
    private StoreCustomerAction $storeCustomerAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeCustomerAction = self::resolve(StoreCustomerAction::class);
    }

    #[Test]
    public function storeMinimalRequiredParams(): void
    {
        $firstname = 'Quincy';
        $lastname = 'Neef';
        $email = 'Quincy@Neef.com';
        $phoneNumber = '+31612345678';

        $phone = new PhoneDTO($phoneNumber);

        $customer = $this->storeCustomerAction->execute(
            Uuid::uuid4(),
            $firstname,
            lastName: $lastname,
            email: $email,
            gender: Gender::MALE->value,
            phone: $phone,
            locale: 'nl-NL',
            paymentType: PaymentType::CREDIT,
            organization: null,
            department: null,
            cocNumber: null,
            vat_number: null,
            paymentTerms: 14,
        );

        self::assertDatabaseHas('customers', [
            'uuid' => $customer->uuid,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone_country_code' => $phone->getCountryCode(),
            'phone_area_code' => $phone->getAreaCode(),
            'phone_subscriber_number' => $phone->getNumber(),
        ]);
    }

    #[DataProvider('storeAllParamsDatasets')]
    #[Test]
    public function storeAllParams(
        string $firstname,
        string $lastname,
        string $email,
        string $phoneNumber,
        string $locale,
        string $organization,
        string $department,
        string $cocNumber,
        string $vatNumber,
        string $gender,
        string $purchaseReference,
        int $paymentTerms,
        PaymentType $paymentType,
        int $creditLimit,
        ?string $note,
        ?CarbonImmutable $customerSince = null,
    ): void {
        $phone = new PhoneDTO($phoneNumber);

        $customer = $this->storeCustomerAction->execute(
            Uuid::uuid4(),
            $firstname,
            $lastname,
            $email,
            $gender,
            $phone,
            $locale,
            $paymentType,
            $organization,
            $department,
            $cocNumber,
            $vatNumber,
            $paymentTerms,
            $purchaseReference,
            $creditLimit,
            $note,
            $customerSince,
        );

        self::assertDatabaseHas('customers', [
            'uuid' => $customer->uuid,
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email' => $email,
            'phone_country_code' => $phone->getCountryCode(),
            'phone_area_code' => $phone->getAreaCode(),
            'phone_subscriber_number' => $phone->getNumber(),
            'department' => $department,
            'organization' => $organization,
            'coc_number' => $cocNumber,
            // VAT numbers should always be stored in upper case, so test that. The customer model does this automatically.
            'vat_number' => strtoupper($vatNumber),
            'gender' => $gender,
            'purchase_reference' => $purchaseReference,
            'terms_of_payment' => $paymentTerms,
            'payment_type' => $paymentType,
            'credit_limit' => $creditLimit,
            'customer_since' => $customerSince ?? CarbonImmutable::today(),
        ]);
    }

    /**
     * @return array<array<string,string|bool|int|float|null|PaymentType|CarbonImmutable>>
     */
    public static function storeAllParamsDatasets(): array
    {
        return [
            [
                'firstname' => 'Quincy',
                'lastname' => 'Neef',
                'email' => 'Quincy@Neef.com',
                'phoneNumber' => '+31612345678',
                'locale' => 'nl-NL',
                'organization' => 'Sandwave',
                'department' => 'DevTeam',
                'cocNumber' => '234232443',
                'vatNumber' => 'adsfasdfasd22',
                'gender' => Gender::MALE->value,
                'purchaseReference' => 'this is a test',
                'paymentTerms' => 14,
                'paymentType' => PaymentType::CREDIT,
                'creditLimit' => 100,
                'note' => null,
            ],
            [
                'firstname' => 'Test#1',
                'lastname' => 'Test#2',
                'email' => 'test3@test.com',
                'phoneNumber' => '+31622345678',
                'locale' => 'nl-NL',
                'organization' => 'Test#4',
                'department' => 'test#5',
                'cocNumber' => '2as34fas232443',
                'vatNumber' => '34GAFSDA',
                'gender' => Gender::FEMALE->value,
                'purchaseReference' => '#erwew',
                'paymentTerms' => 30,
                'paymentType' => PaymentType::DIRECT,
                'creditLimit' => 0,
                'note' => 'a exciting note test#121',
                'customerSince' => new CarbonImmutable('2020-08-11'),
            ],
        ];
    }
}
