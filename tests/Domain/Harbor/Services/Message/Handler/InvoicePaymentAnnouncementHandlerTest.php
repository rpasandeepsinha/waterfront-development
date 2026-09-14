<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message\Handler;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\InvoicePaymentAnnouncement;
use Tests\Factories\InvoiceFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Harbor\Services\Message\Handler\InvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;

#[CoversClass(InvoicePaymentAnnouncementHandler::class)]
class InvoicePaymentAnnouncementHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function handleWithUnknownInvoice(): void
    {
        $message = new InvoicePaymentAnnouncement(
            [123],
            time(),
        );

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Unknown invoice id'),
            );

        $handler = new InvoicePaymentAnnouncementHandler(
            self::createStub(InvoiceRepository::class),
            $logger,
        );
        $handler->handle($message);
    }

    #[Test]
    public function handleUpdatesPaymentAnnounced(): void
    {
        $alreadyAnnouncedAt = CarbonImmutable::yesterday()->getTimestamp();
        $newAnnouncedAt = CarbonImmutable::now()->getTimestamp();

        $message = new InvoicePaymentAnnouncement(
            [222, 333],
            $newAnnouncedAt,
        );

        $product = new ProductFactory()->nlDomain()->createOne();

        $invoice1 = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->makeOne([
                'id' => 222,
                'announced_by_harbor_at' => $alreadyAnnouncedAt,
            ]);

        $invoice2 = new InvoiceFactory()
            ->withCustomer()
            ->for($product)
            ->makeOne([
                'id' => 333,
                'announced_by_harbor_at' => null,
            ]);

        $invoiceRepository = self::createMock(InvoiceRepository::class);
        $invoiceRepository
            ->expects(self::exactly(2))
            ->method('findById')
            ->willReturnCallback(
                fn (int $id): Invoice => match ($id) {
                    222 => $invoice1,
                    333 => $invoice2,
                    default => self::fail(sprintf('Unexpected invoice id %s', $id)),
                },
            );

        $handler = new InvoicePaymentAnnouncementHandler(
            $invoiceRepository,
            self::createStub(LoggerInterface::class),
        );
        $handler->handle($message);

        $invoice1->refresh();
        $invoice2->refresh();
        self::assertSame($alreadyAnnouncedAt, $invoice1->announced_by_harbor_at?->getTimestamp());
        self::assertSame($newAnnouncedAt, $invoice2->announced_by_harbor_at?->getTimestamp());
    }
}
