<?php

declare(strict_types=1);

namespace Tests\Domain\Lighthouse\Unit;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerMetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\RecoveryAddresses;
use SandwaveIo\LighthouseAuthBase\Identity\Traits\IdentityTraits;
use SandwaveIo\LighthouseAuthBase\Identity\VerifiableAddresses;
use Waterfront\Domain\Lighthouse\Actions\GetIdentitiesForCustomerNumberAction;
use Waterfront\Domain\Lighthouse\DTO\IdentityState;
use Waterfront\Domain\Lighthouse\Exceptions\FailedToFetchIdentitiesForCustomerNumbers;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;

#[CoversClass(GetIdentitiesForCustomerNumberAction::class)]
class GetIdentitiesForCustomerNumberActionTest extends TestCase
{
    protected LighthouseApiService&MockObject $lighthouseApiService;

    protected GetIdentitiesForCustomerNumberAction $getIdentitiesForCustomerNumberAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lighthouseApiService = self::createMock(LighthouseApiService::class);

        $logger = self::createStub(LoggerInterface::class);
        $this->getIdentitiesForCustomerNumberAction = new GetIdentitiesForCustomerNumberAction(
            $this->lighthouseApiService,
            $logger,
        );
    }

    #[Test]
    public function getIdentitiesForCustomerNumberFailed(): void
    {
        $customerNumber = 123;

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentitiesByCustomerNumber')
            ->with($customerNumber)
            ->willThrowException(new LighthouseException());

        self::expectException(FailedToFetchIdentitiesForCustomerNumbers::class);

        $this->getIdentitiesForCustomerNumberAction->execute($customerNumber);
    }

    #[Test]
    public function getIdentitiesForCustomerNumberReturnsCustomer(): void
    {
        $customerNumber = 123;

        $identity = $this->provideIdentity(Uuid::uuid4()->toString(), 'test@test.nl', $customerNumber);

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('getKratosIdentitiesByCustomerNumber')
            ->with($customerNumber)
            ->willReturn([
                $identity,
            ]);

        $result = $this->getIdentitiesForCustomerNumberAction->execute($customerNumber);

        self::assertSame($identity->traits->email, $result[0]->traits->email);
    }

    private function provideIdentity(string $uuid, string $email, int $customerNumber): Identity
    {
        return new Identity(
            $uuid,
            SchemaId::CUSTOMER,
            'uuuuuurl',
            IdentityState::ACTIVE->value,
            new IdentityTraits($email, null),
            [new VerifiableAddresses(null, $email, true, null, null, null, null, null)],
            [new RecoveryAddresses($uuid, $email, 'email', 'date', 'date')],
            CarbonImmutable::now(),
            CarbonImmutable::now(),
            null,
            new CustomerMetadataPublic([$customerNumber], [], ['waterfront'], null, null, null),
            null,
        );
    }
}
