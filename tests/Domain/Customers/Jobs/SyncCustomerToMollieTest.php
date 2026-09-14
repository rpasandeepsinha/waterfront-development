<?php

declare(strict_types=1);

namespace Tests\Domain\Customers\Jobs;

use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Jobs\SyncCustomerToMollieJob;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerResponseDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;

#[CoversClass(SyncCustomerToMollieJob::class)]
class SyncCustomerToMollieTest extends IntegrationTestCase
{
    private Customer $customerWithMollie;

    private Customer $customerWithoutMollie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerWithMollie = new CustomerFactory()->withMollieCustomer([])->createOne();
        $this->customerWithoutMollie = new CustomerFactory()->createOne();
    }

    #[Test]
    public function syncMollieCustomerJob(): void
    {
        $manager = self::createMock(MollieCustomerManager::class);
        $response = new MollieCustomerResponseDTO(
            id: '1',
            mode: 'test',
            name: 'tester',
            email: 'test@test.nl',
            locale: 'nl_NL',
            metadata: null,
            createdAt: CarbonImmutable::now(),
        );

        $customer = $this->customerWithMollie;

        $manager->expects(self::once())->method('updateCustomer')->willReturn($response);

        $mollieCustomer = $customer->mollieCustomer;

        self::assertInstanceOf(MollieCustomer::class, $mollieCustomer);

        $job = new SyncCustomerToMollieJob($mollieCustomer);

        $job->handle($manager, self::resolve(LoggerInterface::class));
    }

    #[Test]
    public function updateCustomerWithMollieCustomer(): void
    {
        $manager = self::createMock(MollieCustomerManager::class);
        $response = new MollieCustomerResponseDTO(
            id: '1',
            mode: 'test',
            name: 'tester',
            email: 'test@test.nl',
            locale: 'nl_NL',
            metadata: null,
            createdAt: CarbonImmutable::now(),
        );

        $customer = $this->customerWithMollie;

        $manager->expects(self::once())->method('updateCustomer')->willReturn($response);

        $this->app->bind(MollieCustomerManager::class, fn (): MollieCustomerManager => $manager);

        $customer->email = 'testWith@sandwave.io';
        $customer->save();
    }

    #[Test]
    public function updateCustomerWithMollieCustomerShouldntFailWithMollieCustomerException(): void
    {
        $manager = self::createMock(MollieCustomerManager::class);
        $customer = $this->customerWithMollie;

        $manager
            ->expects(self::once())
            ->method('updateCustomer')
            ->willThrowException(new MollieCustomerApiException(
                status: 404,
                title: 'title',
                detail: '',
                field: null,
                previous: new Exception('placeholder'),
            ));

        $this->app->bind(MollieCustomerManager::class, fn (): MollieCustomerManager => $manager);

        $customer->email = 'testWith@sandwave.io';
        $customer->save();
    }

    #[Test]
    public function updateCustomerWithoutMollieCustomer(): void
    {
        Queue::fake();

        $customer = $this->customerWithoutMollie;
        $customer->email = 'testWithout@sandwave.io';
        $customer->save();

        Queue::assertNotPushed(SyncCustomerToMollieJob::class);
    }

    #[Test]
    public function updateCustomerIrrelevantData(): void
    {
        Queue::fake();

        $customer = $this->customerWithoutMollie;
        $customer->touch();

        Queue::assertNotPushed(SyncCustomerToMollieJob::class);
    }
}
