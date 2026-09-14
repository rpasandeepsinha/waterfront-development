<?php

declare(strict_types=1);

namespace Tests\Domain\Admin\Integration\Unit;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use SandwaveIo\LighthouseAuthBase\Identity\Metadata\CustomerMetadataPublic;
use SandwaveIo\LighthouseAuthBase\Identity\RecoveryAddresses;
use SandwaveIo\LighthouseAuthBase\Identity\Traits\IdentityTraits;
use SandwaveIo\LighthouseAuthBase\Identity\VerifiableAddresses;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Admin\Actions\AnonymizeEmailHistoryForReceiverUuidAction;
use Waterfront\Domain\Admin\Actions\AnonymizeIdentitiesForCustomerAction;
use Waterfront\Domain\Lighthouse\Actions\GetIdentitiesForCustomerNumberAction;
use Waterfront\Domain\Lighthouse\Actions\RemoveCustomerNumberFromIdentityAction;
use Waterfront\Domain\Lighthouse\DTO\IdentityState;
use Waterfront\Domain\Lighthouse\Exceptions\DetachCustomerNumberFromIdentityFailedException;
use Waterfront\Domain\Lighthouse\Exceptions\FailedToFetchIdentitiesForCustomerNumbers;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;

#[CoversClass(AnonymizeIdentitiesForCustomerAction::class)]
#[AllowMockObjectsWithoutExpectations]
class AnonymizeIdentitiesActionTest extends TestCase
{
    protected AnonymizeIdentitiesForCustomerAction $anonymizeIdentitiesForCustomerAction;

    protected GetIdentitiesForCustomerNumberAction&MockObject $getIdentitiesForCustomerNumberAction;

    protected RemoveCustomerNumberFromIdentityAction&MockObject $removeCustomerNumberFromIdentityAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getIdentitiesForCustomerNumberAction = self::createMock(GetIdentitiesForCustomerNumberAction::class);
        $this->removeCustomerNumberFromIdentityAction = self::createMock(RemoveCustomerNumberFromIdentityAction::class);

        $this->anonymizeIdentitiesForCustomerAction = new AnonymizeIdentitiesForCustomerAction(
            $this->getIdentitiesForCustomerNumberAction,
            $this->removeCustomerNumberFromIdentityAction,
            self::createStub(AnonymizeEmailHistoryForReceiverUuidAction::class),
        );
    }

    #[Test]
    public function failedToFetchIdentitiesFromLighthouse(): void
    {
        $this->getIdentitiesForCustomerNumberAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new FailedToFetchIdentitiesForCustomerNumbers());

        self::expectException(ResourceNotFoundException::class);

        $this->anonymizeIdentitiesForCustomerAction->execute(123);
    }

    #[Test]
    public function failedToRemoveIdentity(): void
    {
        $customerNumber = 123;

        $identity = $this->provideIdentity(Uuid::uuid4()->toString(), 'test@test.nl', $customerNumber);

        $this->getIdentitiesForCustomerNumberAction->expects(self::once())->method('execute')->willReturn([$identity]);

        $this->removeCustomerNumberFromIdentityAction
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new DetachCustomerNumberFromIdentityFailedException());

        self::expectException(AnonymizeCustomerException::class);

        $this->anonymizeIdentitiesForCustomerAction->execute(123);
    }

    #[Test]
    public function removeIdentitySuccess(): void
    {
        $customerNumber = 123;

        $identity = $this->provideIdentity(Uuid::uuid4()->toString(), 'test@test.nl', $customerNumber);

        $this->getIdentitiesForCustomerNumberAction->expects(self::once())->method('execute')->willReturn([$identity]);

        $this->removeCustomerNumberFromIdentityAction->expects(self::once())->method('execute');

        $this->anonymizeIdentitiesForCustomerAction->execute(123);
    }

    #[Test]
    public function removeMultipleIdentitiesSuccess(): void
    {
        $customerNumber = 123;

        $identity = $this->provideIdentity(Uuid::uuid4()->toString(), 'test@test.nl', $customerNumber);
        $identity2 = $this->provideIdentity(Uuid::uuid4()->toString(), 'test+2@test.nl', $customerNumber);
        $identity3 = $this->provideIdentity(Uuid::uuid4()->toString(), 'test+3@test.nl', $customerNumber);
        $identity4 = $this->provideIdentity(Uuid::uuid4()->toString(), 'test+4@test.nl', $customerNumber);

        $this->getIdentitiesForCustomerNumberAction
            ->expects(self::once())
            ->method('execute')
            ->willReturn([$identity, $identity2, $identity3, $identity4]);

        $this->removeCustomerNumberFromIdentityAction->expects(self::exactly(4))->method('execute');

        $this->anonymizeIdentitiesForCustomerAction->execute(123);
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
            [],
            new CustomerMetadataPublic([$customerNumber], [], ['waterfront'], null, null, null),
            null,
        );
    }
}
