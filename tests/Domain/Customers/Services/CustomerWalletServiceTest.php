<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Services;

use Illuminate\Support\Collection;
use Illuminate\Testing\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerWalletFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Domain\Customers\Services\CustomerWalletService;

#[CoversClass(CustomerWalletService::class)]
class CustomerWalletServiceTest extends IntegrationTestCase
{
    #[Test]
    public function requestRefundCsv(): void
    {
        $customer1 = new CustomerFactory()->createOne();
        $wallet1 = new CustomerWalletFactory()->for($customer1)->createOne();

        $customer2 = new CustomerFactory()->createOne();
        $wallet2 = new CustomerWalletFactory()->for($customer2)->createOne();

        $walletCollection = new Collection([$wallet1, $wallet2]);

        $service = self::resolve(CustomerWalletService::class);
        $response = $service->createRefundCsv($walletCollection);

        // doing -1 to the array cause of the csv column header
        Assert::assertSame(2, count(str_getcsv($response, PHP_EOL, escape: '\\')) - 1);

        $wallet1 = CustomerWallet::where('customer_id', $customer1->id)->first();
        Assert::assertNotNull($wallet1);
        Assert::assertNotNull($wallet1->csv_downloaded_at);
        $wallet2 = CustomerWallet::where('customer_id', $customer2->id)->first();
        Assert::assertNotNull($wallet2);
        Assert::assertNotNull($wallet2->csv_downloaded_at);
    }
}
