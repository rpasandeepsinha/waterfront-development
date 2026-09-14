<?php

declare(strict_types=1);

namespace Tests\Domain\Harbor\Services\Message\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\MandateAnnouncement;
use Tests\Factories\CustomerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Domain\Harbor\Services\Message\Handler\MandateAnnouncementHandler;

#[CoversClass(MandateAnnouncementHandler::class)]
class MandateAnnouncementHandlerTest extends IntegrationTestCase
{
    #[Test]
    public function mandateCreated(): void
    {
        $customer = CustomerFactory::new()->createOne([
            'has_direct_debit' => false,
        ]);

        new MandateAnnouncementHandler(
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        )->handle(new MandateAnnouncement(
            'mdt_sometest123',
            $customer->customer_number,
            true,
        ));

        self::assertTrue($customer->refresh()->has_direct_debit);
    }

    #[Test]
    public function mandateRevoked(): void
    {
        $customer = CustomerFactory::new()->createOne([
            'has_direct_debit' => true,
        ]);

        new MandateAnnouncementHandler(
            self::resolve(LoggerInterface::class),
            self::resolve(CustomerRepository::class),
        )->handle(new MandateAnnouncement(
            'mdt_sometest123',
            $customer->customer_number,
            false,
        ));

        self::assertFalse($customer->refresh()->has_direct_debit);
    }
}
