<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message\Handler;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\WithdrawInvoicePaymentAnnouncement;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Services\Message\Handler\WithdrawInvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;

#[CoversClass(WithdrawInvoicePaymentAnnouncementHandler::class)]
class WithdrawInvoicePaymentAnnouncementHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function handleWithUnknownInvoice(): void
    {
        $message = new WithdrawInvoicePaymentAnnouncement(
            [123],
        );

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Unknown invoice id'),
            );

        $handler = new WithdrawInvoicePaymentAnnouncementHandler(
            self::createStub(InvoiceRepository::class),
            $logger,
        );
        $handler->handle($message);
    }

    #[Test]
    public function handleUpdatesPaymentWithdrawn(): void
    {
        $message = new WithdrawInvoicePaymentAnnouncement(
            [555, 444],
        );

        $product = new ProductFactory()->nlDomain()->createOne();

        $invoice1 = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->makeOne([
                'id' => 444,
                'announced_by_harbor_at' => null,
            ]);

        $invoice2 = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->makeOne([
                'id' => 555,
                'announced_by_harbor_at' => CarbonImmutable::now(),
            ]);

        $invoiceRepository = self::createMock(InvoiceRepository::class);
        $invoiceRepository
            ->expects(self::exactly(2))
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): Invoice => match ($id) {
                    444 => $invoice1,
                    555 => $invoice2,
                    default => self::fail(sprintf('Unexpected invoice id %s', $id)),
                },
            );

        $handler = new WithdrawInvoicePaymentAnnouncementHandler(
            $invoiceRepository,
            self::createStub(LoggerInterface::class),
        );
        $handler->handle($message);

        $invoice1->refresh();
        $invoice2->refresh();
        self::assertNull($invoice1->announced_by_harbor_at);
        self::assertNull($invoice2->announced_by_harbor_at);
    }
}
