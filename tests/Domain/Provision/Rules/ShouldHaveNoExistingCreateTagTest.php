<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Rules;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Rules\ShouldHaveNoExistingCreateTag;

#[CoversClass(ShouldHaveNoExistingCreateTag::class)]
class ShouldHaveNoExistingCreateTagTest extends TestCase
{
    private ProvisioningRequestRepository&MockObject $mockRequestRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockRequestRepository = self::createMock(ProvisioningRequestRepository::class);
        $this->app->bind(ProvisioningRequestRepository::class, fn () => $this->mockRequestRepository);
    }

    #[Test]
    public function validateFailsWhenValueIsNotUuid(): void
    {
        $rule = new ShouldHaveNoExistingCreateTag(ProvisionType::HOSTING);

        $this->mockRequestRepository->expects(self::never())->method('createRequestExists');

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $rule->validate('tag', 'not-a-uuid', $fail);

        self::assertSame('The tag must be a valid UUID string.', $failMessage);
    }

    #[Test]
    public function validateFailsWhenCreateRequestAlreadyExistsForTagAndType(): void
    {
        $tag = Uuid::uuid4()->toString();

        $this->mockRequestRepository
            ->expects(self::once())
            ->method('createRequestExists')
            ->with($tag)
            ->willReturn(true);

        $rule = new ShouldHaveNoExistingCreateTag(ProvisionType::HOSTING);

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $rule->validate('tag', $tag, $fail);

        self::assertSame(
            'A create request for tag already exists in the [hosting] type.',
            $failMessage,
        );
    }

    #[Test]
    public function validatePassesWhenNoCreateRequestExistsForTag(): void
    {
        $tag = Uuid::uuid4()->toString();

        $this->mockRequestRepository
            ->expects(self::once())
            ->method('createRequestExists')
            ->with($tag)
            ->willReturn(false);

        $rule = new ShouldHaveNoExistingCreateTag(ProvisionType::BACKUP);

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $rule->validate('tag', $tag, $fail);

        self::assertNull($failMessage);
    }

    /**
     * @phpstan-ignore ergebnis.noParameterPassedByReference
     */
    private function makeFailClosure(?string &$failMessage): Closure
    {
        return static function (string $message) use (&$failMessage): void {
            $failMessage = $message;
        };
    }
}
