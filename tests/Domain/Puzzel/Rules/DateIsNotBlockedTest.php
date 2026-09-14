<?php

declare(strict_types=1);

namespace Tests\Domain\Puzzel\Rules;

use Carbon\CarbonImmutable;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;
use Waterfront\Domain\Puzzel\Repositories\PuzzelBlockedDateRepository;
use Waterfront\Domain\Puzzel\Rules\DateIsNotBlocked;
use Waterfront\Infra\Translation\Translator as WaterfrontTranslator;

#[CoversClass(DateIsNotBlocked::class)]
class DateIsNotBlockedTest extends TestCase
{
    private PuzzelBlockedDateRepository&MockObject $repository;

    private WaterfrontTranslator $translator;

    private DateIsNotBlocked $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(PuzzelBlockedDateRepository::class);
        $this->translator = $this->createStub(WaterfrontTranslator::class);
        $this->rule = new DateIsNotBlocked($this->translator, $this->repository);
    }

    #[Test]
    public function validationFailsWhenDateIsBlocked(): void
    {
        $this->translator = $this->createMock(WaterfrontTranslator::class);
        $this->rule = new DateIsNotBlocked($this->translator, $this->repository);
        $fail = false;

        $this->repository->expects(self::once())->method('dateIsBlocked')->willReturn(true);

        $this->translator
            ->expects(self::once())
            ->method('translate')
            ->with('validation.puzzel-date-blocked')
            ->willReturn('validation.puzzel-date-blocked');

        $this->rule->validate('date', CarbonImmutable::now()->format('Y-m-d'), function (
            string $message,
            ?string $attribute = null,
        ) use (&$fail) {
            self::assertSame('validation.puzzel-date-blocked', $message);
            $fail = true;

            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });

        self::assertTrue($fail);
    }

    #[Test]
    public function validationFailsWithNonDate(): void
    {
        $fail = false;

        $this->repository->expects(self::never())->method('dateIsBlocked')->willReturn(true);

        $this->rule->validate('date', 12, function (string $message, ?string $attribute = null) use (&$fail) {
            self::assertSame('validation.date', $message);
            $fail = true;

            return new PotentiallyTranslatedString('fail', $this->app->make(Translator::class));
        });

        self::assertTrue($fail);
    }
}
