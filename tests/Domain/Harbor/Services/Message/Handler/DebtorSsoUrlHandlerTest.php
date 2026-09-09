<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\DebtorSsoUrl;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtorSsoUrlHandler;

#[CoversClass(DebtorSsoUrlHandler::class)]
class DebtorSsoUrlHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function handleWithUnknownCustomer(): void
    {
        $message = new DebtorSsoUrl(
            123,
            'https://example.com/sso/123',
            'https://example.com/admin/123',
        );

        $customerRepository = self::createMock(CustomerRepository::class);
        $customerRepository
            ->expects(self::once())
            ->method('findByCustomerNumber')
            ->with(123)
            ->willReturn(null);

        $handler = new DebtorSsoUrlHandler(
            $customerRepository,
            self::createStub(LoggerInterface::class),
        );

        $handler->handle($message);
    }

    #[Test]
    public function handleUpdatesDebtorUrlsForCustomer(): void
    {
        $message = new DebtorSsoUrl(
            123,
            'https://example.com/sso/123',
            'https://example.com/admin/123',
        );

        $customer = new CustomerFactory()->createOne([ 'invoice_history_url' => '', 'admin_url' => null ]);

        $customerRepository = self::createMock(CustomerRepository::class);
        $customerRepository
            ->expects(self::once())
            ->method('findByCustomerNumber')
            ->with(123)
            ->willReturn($customer);

        $handler = new DebtorSsoUrlHandler(
            $customerRepository,
            self::createStub(LoggerInterface::class),
        );

        $handler->handle($message);

        self::assertSame($message->getDebtorSsoUrl(), $customer->invoice_history_url);
        self::assertSame($message->getAdminUrl(), $customer->admin_url);
    }
}
