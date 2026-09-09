<?php

declare(strict_types=1);

namespace Tests\Domain\Microsoft365\Actions;

use PHPUnit\Framework\Attributes\CoversClass;
use SandwaveIo\Office365\Exception\Office365Exception;
use Tests\Factories\CustomerFactory;
use Tests\Factories\Microsoft365CustomerInfoFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Actions\RetryCreateKpnCustomerAction;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;

#[CoversClass(RetryCreateKpnCustomerAction::class)]
class RetryCreateKpnCustomerActionTest extends IntegrationTestCase
{
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = new CustomerFactory()->createOne();
    }

    public function testExecuteReturnsTrueWhenKpnCustomerCreated(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->with(
                self::callback(fn (Customer $customer): bool => $customer->id === $this->customer->id),
                (string) $customerInfo->id,
            )
            ->willReturn(true);

        $successful = new RetryCreateKpnCustomerAction($microsoft365Service)->execute($customerInfo);

        self::assertTrue($successful);
        self::assertDatabaseHas(Microsoft365CustomerInfo::class, [
            'id' => $customerInfo->id,
            'technical_status' => Microsoft365ProcessStatus::ACTIVE->value,
        ]);
    }

    public function testExecuteSetsFailedStatusWhenCreationUnsuccessful(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
            'technical_status' => Microsoft365ProcessStatus::ACTIVE,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->willReturn(false);

        $successful = new RetryCreateKpnCustomerAction($microsoft365Service)->execute($customerInfo);

        self::assertFalse($successful);
        self::assertDatabaseHas(Microsoft365CustomerInfo::class, [
            'id' => $customerInfo->id,
            'technical_status' => Microsoft365ProcessStatus::FAILED->value,
        ]);
    }

    public function testExecuteSkipsWhenKpnCustomerIdAlreadySet(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => 'CID12345',
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::never())
            ->method('createKpnCustomer');

        $successful = new RetryCreateKpnCustomerAction($microsoft365Service)->execute($customerInfo);

        self::assertTrue($successful);
    }

    public function testExecutePropagatesOffice365Exception(): void
    {
        $customerInfo = new Microsoft365CustomerInfoFactory()->for($this->customer)->createOne([
            'kpn_customer_id' => null,
        ]);

        $microsoft365Service = self::createMock(Microsoft365Service::class);
        $microsoft365Service->expects(self::once())
            ->method('createKpnCustomer')
            ->willThrowException(new Office365Exception('KPN customer creation failed'));

        $this->expectException(Office365Exception::class);

        new RetryCreateKpnCustomerAction($microsoft365Service)->execute($customerInfo);
    }
}
