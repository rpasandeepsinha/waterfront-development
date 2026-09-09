<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Rules;

use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;
use Waterfront\Domain\Puzzel\Models\PuzzelCallbackTimeslot;
use Waterfront\Domain\Puzzel\Repositories\PuzzelCallbackTimeslotRepository;
use Waterfront\Domain\Puzzel\Rules\TimeslotExistsWithCapacity;
use Waterfront\Infra\Translation\Translator as WaterfrontTranslator;

#[CoversClass(TimeslotExistsWithCapacity::class)]
class TimeslotExistsWithCapacityTest extends TestCase
{
    private PuzzelCallbackTimeslotRepository $mockTimeslotRepository;

    private WaterfrontTranslator $mockTranslator;

    private TimeslotExistsWithCapacity $rule;

    public function setUp(): void
    {
        parent::setUp();
        $this->mockTimeslotRepository = self::createStub(PuzzelCallbackTimeslotRepository::class);
        $this->mockTranslator = self::createStub(WaterfrontTranslator::class);
        $this->rule = new TimeslotExistsWithCapacity($this->mockTranslator, $this->mockTimeslotRepository);
    }

    #[Test]
    public function invalidStringError(): void
    {
        $fail = false;

        $this->rule->validate('attribute', 123, function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.uuid', $message);
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });
        self::assertTrue($fail);
    }

    #[Test]
    public function invalidUuidError(): void
    {
        $fail = false;
        $this->rule->validate('attribute', 'not-a-uuid', function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.uuid', $message);
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });
        self::assertTrue($fail);
    }

    #[Test]
    public function timeslotDoesntExists(): void
    {
        $this->mockTimeslotRepository = self::createMock(PuzzelCallbackTimeslotRepository::class);
        $this->rule = new TimeslotExistsWithCapacity($this->mockTranslator, $this->mockTimeslotRepository);

        $uuid = Uuid::uuid4();

        $this->mockTimeslotRepository->expects($this->once())
            ->method('getByUuid')
            ->willReturn(null);

        $fail = false;
        $this->rule->validate('attribute', $uuid->toString(), function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.exists', $message);
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });
        self::assertTrue($fail);
    }

    #[Test]
    public function timeslotCapacityIsFull(): void
    {
        $this->mockTimeslotRepository = self::createMock(PuzzelCallbackTimeslotRepository::class);
        $this->mockTranslator = self::createMock(WaterfrontTranslator::class);
        $this->rule = new TimeslotExistsWithCapacity($this->mockTranslator, $this->mockTimeslotRepository);

        $uuid = Uuid::uuid4();

        $this->mockTimeslotRepository->expects($this->once())
            ->method('getByUuid')
            ->willReturn($this->createStub(PuzzelCallbackTimeslot::class));

        $this->mockTimeslotRepository->expects($this->once())
            ->method('hasAvailableCapacity')
            ->with($this->createStub(PuzzelCallbackTimeslot::class))
            ->willReturn(false);

        $this->mockTranslator->expects($this->once())
            ->method('translate')
            ->with('validation.puzzel-timeslot-full')
            ->willReturn('validation.puzzel-timeslot-full');

        $fail = false;
        $this->rule->validate('attribute', $uuid->toString(), function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.puzzel-timeslot-full', $message);
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });
        self::assertTrue($fail);
    }

    #[Test]
    public function validationPasses(): void
    {
        $this->mockTimeslotRepository = self::createMock(PuzzelCallbackTimeslotRepository::class);
        $this->rule = new TimeslotExistsWithCapacity($this->mockTranslator, $this->mockTimeslotRepository);

        $uuid = Uuid::uuid4();

        $this->mockTimeslotRepository->expects($this->once())
            ->method('getByUuid')
            ->willReturn($this->createStub(PuzzelCallbackTimeslot::class));

        $this->mockTimeslotRepository->expects($this->once())
            ->method('hasAvailableCapacity')
            ->with($this->createStub(PuzzelCallbackTimeslot::class))
            ->willReturn(true);

        $fail = false;
        $this->rule->validate('attribute', $uuid->toString(), function () use (&$fail) {
            $fail = true;
            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });
        self::assertFalse($fail, 'Validation should pass');
    }
}
