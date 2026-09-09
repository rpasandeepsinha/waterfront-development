<?php

declare(strict_types=1);

namespace Tests\Domain\Lighthouse\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Lighthouse\Actions\RemoveCustomerNumberFromIdentityAction;
use Waterfront\Domain\Lighthouse\Exceptions\DetachCustomerNumberFromIdentityFailedException;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;

#[CoversClass(RemoveCustomerNumberFromIdentityAction::class)]
class RemoveCustomerNumberFromIdentityActionTest extends TestCase
{
    protected LighthouseApiService&MockObject $lighthouseApiService;

    protected RemoveCustomerNumberFromIdentityAction $removeCustomerNumberFromIdentityAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lighthouseApiService = self::createMock(LighthouseApiService::class);

        $logger = self::createStub(LoggerInterface::class);
        $this->removeCustomerNumberFromIdentityAction = new RemoveCustomerNumberFromIdentityAction($this->lighthouseApiService, $logger);
    }

    #[Test]
    public function removeCustomerNumberFromIdentityFailed(): void
    {
        $uuid = Uuid::uuid4();
        $customerNumber = 123;

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('detachIdentityForBusinessUnit')
            ->with($uuid, $customerNumber)
            ->willThrowException(new LighthouseException());

        self::expectException(DetachCustomerNumberFromIdentityFailedException::class);

        $this->removeCustomerNumberFromIdentityAction->execute($uuid, $customerNumber);
    }

    #[Test]
    public function removeCustomerNumberFromIdentity(): void
    {
        $uuid = Uuid::uuid4();
        $customerNumber = 123;

        $this->lighthouseApiService
            ->expects(self::once())
            ->method('detachIdentityForBusinessUnit')
            ->with($uuid, $customerNumber);

        $this->removeCustomerNumberFromIdentityAction->execute($uuid, $customerNumber);
    }
}
