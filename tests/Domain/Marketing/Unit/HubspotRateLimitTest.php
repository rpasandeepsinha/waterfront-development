<?php

declare(strict_types=1);

namespace Tests\Domain\Marketing\Unit;

use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\TestCase;
use Waterfront\Domain\Marketing\Factory\HubspotContactFactory;
use Waterfront\Domain\Marketing\HubspotEvents\HubspotEventRepository;
use Waterfront\Domain\Marketing\Jobs\AnonymizeContactInHubspotJob;
use Waterfront\Infra\HubspotClient\ContactsClient;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;

#[CoversClass(AnonymizeContactInHubspotJob::class)]
class HubspotRateLimitTest extends TestCase
{
    #[Test]
    public function rateLimitExceededSleepsCommand(): void
    {
        Sleep::fake();

        $customer = new CustomerFactory()->makeOne();
        $customer->id = 1;

        $hubspotEventRepository = self::createStub(HubspotEventRepository::class);
        $hubspotContactsClient = self::createMock(ContactsClient::class);
        $hubspotContactsClient->expects(self::once())
            ->method('findBySandwaveUuid')
            ->willThrowException(new HubspotThrottledException());

        $command = new AnonymizeContactInHubspotJob($customer);
        $command->handle(
            $hubspotContactsClient,
            $hubspotEventRepository,
            new HubspotContactFactory()
        );

        Sleep::assertSleptTimes(1);
    }

    #[Test]
    public function rateLimitNotExceededDoesNotSleep(): void
    {
        Sleep::fake();

        $customer = new CustomerFactory()->makeOne();
        $customer->id = 1;

        $hubspotEventRepository = self::createStub(HubspotEventRepository::class);
        $hubspotContactsClient = self::createStub(ContactsClient::class);

        $command = new AnonymizeContactInHubspotJob($customer);
        $command->handle(
            $hubspotContactsClient,
            $hubspotEventRepository,
            new HubspotContactFactory()
        );

        Sleep::assertNeverSlept();
    }
}
