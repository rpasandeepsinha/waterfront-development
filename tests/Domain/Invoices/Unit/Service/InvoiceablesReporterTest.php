<?php

declare(strict_types=1);

namespace Tests\Domain\Invoices\Unit\Service;

use Carbon\CarbonImmutable;
use Illuminate\Log\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Domain\Invoices\Services\InvoiceablesReporter;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(InvoiceablesReporter::class)]
class InvoiceablesReporterTest extends TestCase
{
    #[Test]
    public function report(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::now()
        );

        $invoiceRepository = self::createMock(InvoiceRepository::class);
        $invoiceRepository->expects(self::once())
            ->method('countNotSentToHarbor')
            ->willReturn(3);

        $invoiceRepository->expects(self::once())
            ->method('countMigratedNotSentToHarbor')
            ->willReturn(1);

        $subscriptionPartnerRepository = self::createMock(SubscriptionRepository::class);

        $subscriptionPartnerRepository
            ->expects(self::once())
            ->method('countDueForInvoicing')
            ->with(CarbonImmutable::getTestNow())
            ->willReturn(5);

        $logger = self::createMock(Logger::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Daily invoiceables reporting',
                [ LoggingContextKeys::REPORTING_DATA => [
                    'invoices' => 3,
                    'subscriptions' => 5,
                    'migrated' => 1,
                ]],
            );

        $reporter = new InvoiceablesReporter(
            $invoiceRepository,
            $subscriptionPartnerRepository,
            $logger,
        );

        $reporter->report();
    }
}
