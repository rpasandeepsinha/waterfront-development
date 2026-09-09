<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Customers\Actions;

use DateTime;
use DateTimeImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Factories\CustomerFactory;
use Tests\Factories\CustomerWalletFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Customers\Actions\NovaCustomerWalletDownloadOverviewCsvAction;
use Waterfront\Domain\Customers\Models\CustomerWallet;
use Waterfront\Infra\Common\DateTimeFormat;

#[CoversClass(NovaCustomerWalletDownloadOverviewCsvAction::class)]
class NovaCustomerWalletDownloadOverviewCsvActionTest extends IntegrationTestCase
{
    private CustomerWallet $wallet1;

    private CustomerWallet $wallet2;

    private CustomerWallet $wallet3;

    private CustomerWallet $wallet4;

    private NovaCustomerWalletDownloadOverviewCsvAction $novaAction;

    private Filesystem|MockObject $filesystem;

    public function setUp(): void
    {
        parent::setUp();

        $customer1 = new CustomerFactory()->createOne();
        $customer2 = new CustomerFactory()->createOne();
        $customer3 = new CustomerFactory()->createOne();
        $customer4 = new CustomerFactory()->createOne();

        $this->wallet1 = new CustomerWalletFactory()->createOne([
            'customer_id' => $customer1,
            'amount' => 100,
            'bank_account_name' => 'testwallet1',
            'bank_account_number' => 'test',
            'refund_requested_at' => new DateTime('2023-01-5'),
            'csv_downloaded_at' => new DateTime('2023-01-15'),
        ]);
        $this->wallet2 = new CustomerWalletFactory()->createOne([
            'customer_id' => $customer2->id,
            'amount' => 200,
            'bank_account_name' => 'testwallet2',
            'bank_account_number' => 'test',
            'refund_requested_at' => new DateTime('2023-01-10'),
            'csv_downloaded_at' => new DateTime('2023-01-20'),
        ]);

        $this->wallet3 = new CustomerWalletFactory()->createOne([
            'customer_id' => $customer3->id,
            'amount' => 300,
            'bank_account_name' => 'testwallet3',
            'bank_account_number' => 'test',
            'refund_requested_at' => new DateTime('2023-01-17'),
            'csv_downloaded_at' => null,
        ]);

        $this->wallet4 = new CustomerWalletFactory()->createOne([
            'customer_id' => $customer4->id,
            'amount' => 400,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'refund_requested_at' => null,
            'csv_downloaded_at' => null,
        ]);
    }

    #[Test]
    public function exportWithReferenceDateOnly(): void
    {
        $refDate = new DateTimeImmutable('2023-01-15');
        $exportAll = false;
        $expectedCSV = <<<CSV
customer_number,bank_account_name,bank_account_number,amount,refund_requested_at,downloaded_at
{$this->wallet1->customer->customer_number},testwallet1,test,100,2023-01-05,2023-01-15
{$this->wallet2->customer->customer_number},testwallet2,test,200,2023-01-10,
{$this->wallet3->customer->customer_number},,,300,,
{$this->wallet4->customer->customer_number},,,400,,

CSV;

        $this->setUpFilesystemMock($refDate, $expectedCSV);

        /** @var array<string> $result */
        $result = $this->novaAction->handle(
            new ActionFields(
                new Collection([
                    'ref_date' => $refDate->format(DateTimeFormat::DATE),
                    'export_all' => $exportAll,
                ]),
                new Collection([])
            )
        );

        self::assertArrayHasKey('download', $result);
    }

    public function setUpFilesystemMock(DateTimeImmutable $refDate, string $expectedCSV): void
    {
        $this->filesystem = self::createMock(Filesystem::class);
        $this->app->instance(Filesystem::class, $this->filesystem);

        $fileManager = self::createStub(FilesystemManager::class);
        $fileManager
            ->method('disk')
            ->willReturn($this->filesystem);
        $this->app->instance(FilesystemManager::class, $fileManager);

        $this->novaAction = self::resolve(NovaCustomerWalletDownloadOverviewCsvAction::class);

        $this->filesystem->expects(self::once())
            ->method('put')
            ->with(
                self::stringStartsWith(
                    sprintf('exports/customer-wallet-overview-ref-date-%s.csv', $refDate->format(DateTimeFormat::FILENAME))
                ),
                self::equalTo($expectedCSV)
            );
    }
}
