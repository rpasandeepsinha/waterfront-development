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
use Waterfront\Domain\Provision\Rules\ShouldHaveOneExistingCreateTag;

#[CoversClass(ShouldHaveOneExistingCreateTag::class)]
class ShouldHaveOneExistingCreateTagTest extends TestCase
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
        $rule = new ShouldHaveOneExistingCreateTag(ProvisionType::HOSTING);

        $this->mockRequestRepository
            ->expects(self::never())
            ->method('createRequestCount');

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $rule->validate('tag', 'not-a-uuid', $fail);

        self::assertSame('The tag must be a valid UUID string.', $failMessage);
    }

    #[Test]
    public function validateFailsWhenMultipleCreateRequestExistsForTag(): void
    {
        $tag = Uuid::uuid4()->toString();
        $rule = new ShouldHaveOneExistingCreateTag(ProvisionType::HOSTING);

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $this->mockRequestRepository
            ->expects(self::once())
            ->method('createRequestCount')
            ->with($tag)
            ->willReturn(2);

        $rule->validate('tag', $tag, $fail);

        self::assertSame(
            'The tag has multiple create requests linked for [hosting] type.',
            $failMessage
        );
    }

    #[Test]
    public function validateFailsWhenNoCreateRequestExistsForTag(): void
    {
        $tag = Uuid::uuid4()->toString();
        $rule = new ShouldHaveOneExistingCreateTag(ProvisionType::HOSTING);

        $failMessage = null;
        $fail = $this->makeFailClosure($failMessage);

        $this->mockRequestRepository
            ->expects(self::once())
            ->method('createRequestCount')
            ->with($tag)
            ->willReturn(0);

        $rule->validate('tag', $tag, $fail);

        self::assertSame(
            'No create request with this tag in the [hosting] type.',
            $failMessage
        );
    }

    #[Test]
    public function validatePassesWhenOneCreateRequestExistsForTagAndType(): void
    {
        $tag = Uuid::uuid4()->toString();

        $this->mockRequestRepository
            ->expects(self::once())
            ->method('createRequestCount')
            ->with($tag)
            ->willReturn(1);

        $rule = new ShouldHaveOneExistingCreateTag(ProvisionType::HOSTING);

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
