<?php

declare(strict_types=1);

namespace Tests\Domain\Voucher\Services;

use Carbon\CarbonImmutable;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\VoucherFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Voucher\DTO\VoucherDTO;
use Waterfront\Domain\Voucher\Repository\VoucherRepository;
use Waterfront\Domain\Voucher\Services\VoucherService;

#[CoversClass(VoucherService::class)]
class VoucherServiceTest extends IntegrationTestCase
{
    public static function validityProvider(): Generator
    {
        yield [true, true, true];
        yield [false, false, true];
        yield [true, false, false];
        yield [false, true, true];
    }

    #[Test]
    #[DataProvider('validityProvider')]
    public function checkVoucherIsValid(bool $alreadyClaimed, bool $allowMultiple, bool $expectedValidity): void
    {
        $customer = new CustomerFactory()->createOne();
        $voucher = new VoucherFactory()->createOne([
            'expiration_date' => CarbonImmutable::tomorrow(),
            'max_claims' => $alreadyClaimed && ! $allowMultiple ? 0 : 1,
            'allow_multiple_claims_same_customer' => $allowMultiple,
        ]);

        $repoStub = self::createConfiguredStub(
            VoucherRepository::class,
            [
                'findByCode' => $voucher,
                'hasCustomerClaimedVoucher' => $alreadyClaimed,
            ]
        );

        $voucherService = new VoucherService($repoStub);

        self::assertSame($expectedValidity, $voucherService->checkVoucher($voucher, $customer));
    }

    #[Test]
    public function expiredVoucherIsInvalid(): void
    {
        $customer = new CustomerFactory()->createOne();
        $voucher = new VoucherFactory()->createOne([
            'expiration_date' => CarbonImmutable::yesterday(),
            'max_claims' => 1,
            'allow_multiple_claims_same_customer' => true,
        ]);

        $repoStub = self::createConfiguredStub(
            VoucherRepository::class,
            [
                'findByCode' => $voucher,
                'hasCustomerClaimedVoucher' => false,
            ]
        );

        $voucherService = new VoucherService($repoStub);

        self::assertFalse($voucherService->checkVoucher($voucher, $customer));
    }

    #[Test]
    public function updateVoucherPersistsAllowedFields(): void
    {
        $voucher = new VoucherFactory()->createOne([
            'description'     => 'old description',
            'max_claims'      => 5,
            'expiration_date' => CarbonImmutable::tomorrow(),
        ]);

        $dto = new VoucherDTO(
            displayName: $voucher->display_name,
            internalName: $voucher->internal_name,
            description: 'new description',
            code: $voucher->code,
            amount: $voucher->amount,
            amountType: $voucher->amount_type,
            maxClaims: 10,
            billingPeriod: $voucher->billing_period,
            contractPeriod: $voucher->contract_period,
            expirationDate: CarbonImmutable::now()->addDays(7),
            applyWithDiscount: $voucher->apply_with_discount,
            allowMultipleClaimsSameCustomer: $voucher->allow_multiple_claims_same_customer,
            productSlug: null,
            productGroupSlug: null,
        );

        $voucherService = new VoucherService(self::createStub(VoucherRepository::class));
        $voucherService->updateVoucher($voucher, $dto);

        $voucher->refresh();

        self::assertSame('new description', $voucher->description);
        self::assertSame(10, $voucher->max_claims);
        self::assertSame($dto->amount, $voucher->amount);
        self::assertNotNull($voucher->expiration_date);
    }

    #[Test]
    public function updateVoucherAllowsNullableFields(): void
    {
        $voucher = new VoucherFactory()->createOne([
            'description'     => 'old description',
            'max_claims'      => 5,
            'expiration_date' => CarbonImmutable::tomorrow(),
        ]);

        $dto = new VoucherDTO(
            displayName: $voucher->display_name,
            internalName: $voucher->internal_name,
            description: null,
            code: $voucher->code,
            amount: $voucher->amount,
            amountType: $voucher->amount_type,
            maxClaims: null,
            billingPeriod: $voucher->billing_period,
            contractPeriod: $voucher->contract_period,
            expirationDate: null,
            applyWithDiscount: $voucher->apply_with_discount,
            allowMultipleClaimsSameCustomer: $voucher->allow_multiple_claims_same_customer,
            productSlug: null,
            productGroupSlug: null,
        );

        $voucherService = new VoucherService(self::createStub(VoucherRepository::class));
        $voucherService->updateVoucher($voucher, $dto);

        $voucher->refresh();

        self::assertNull($voucher->description);
        self::assertNull($voucher->max_claims);
        self::assertNull($voucher->expiration_date);
    }
}
