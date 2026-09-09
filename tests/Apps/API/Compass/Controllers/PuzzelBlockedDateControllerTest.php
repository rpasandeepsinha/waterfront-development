<?php

declare(strict_types=1);

namespace Tests\Apps\API\Compass\Controllers;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\PuzzelBlockedDateFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\API\Compass\Controllers\PuzzelBlockedDateController;
use Waterfront\Domain\Puzzel\Models\PuzzelBlockedDate;

#[CoversClass(PuzzelBlockedDateController::class)]
class PuzzelBlockedDateControllerTest extends IntegrationTestCase
{
    #[Test]
    public function list(): void
    {
        $pastDate = PuzzelBlockedDateFactory::new()->createOne([
            'date'   => CarbonImmutable::now()->subDay(),
            'reason' => 'past date',
        ]);

        PuzzelBlockedDateFactory::new()
            ->forEachSequence([
                    'date'   => CarbonImmutable::now()->addDay(),
                    'reason' => 'future 1',
                ], [
                    'date'   => CarbonImmutable::now()->addDays(2),
                    'reason' => 'future 2',
                ], [
                    'date'   => CarbonImmutable::now()->addDays(3),
                    'reason' => 'future 3',
                ], [
                    'date'   => CarbonImmutable::now()->addDays(4),
                    'reason' => 'future 4',
                ], [
                    'date'   => CarbonImmutable::now()->addDays(5),
                    'reason' => 'future 5',
                ])
            ->create();

        $todayDate = PuzzelBlockedDateFactory::new()->createOne([
            'date' => CarbonImmutable::now()->addHour(),
        ]);

        $this->actingAsEmployee()
            ->get($this->generateRoute('admin.puzzel.blocked-dates.list'))
            ->assertJsonCount(6, 'data')
            ->assertJsonMissing(
                ['reason' => $pastDate->reason]
            )
            ->assertJsonFragment(['pageSize' => 100])
            ->assertJsonFragment(
                ['reason' => $todayDate->reason],
            )
            ->assertJsonFragment(['reason' => 'future 1'])
            ->assertJsonFragment(['reason' => 'future 2'])
            ->assertJsonFragment(['reason' => 'future 3'])
            ->assertJsonFragment(['reason' => 'future 4'])
            ->assertJsonFragment(['reason' => 'future 5'])
            ->assertOk();
    }

    #[Test]
    public function destroy(): void
    {
        $blockedDate = PuzzelBlockedDateFactory::new()->createOne([
            'date'   => CarbonImmutable::now(),
            'reason' => 'Test reason',
        ]);

        $this->actingAsEmployee()
            ->deleteJson($this->generateRoute('admin.puzzel.blocked-dates.destroy'), ['id' => $blockedDate->id])
            ->assertNoContent();

        $this->assertDatabaseMissing($blockedDate);
    }

    #[Test]
    public function destroyNotFound(): void
    {
        $this->actingAsEmployee()
            ->deleteJson($this->generateRoute('admin.puzzel.blocked-dates.destroy'), ['id' => 1337])
            ->assertUnprocessable();
    }

    #[Test]
    public function store(): void
    {
        $date = CarbonImmutable::now()->addDay();
        $dateNoReason = CarbonImmutable::now()->addDays(2);
        $reason = 'Holiday';

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.puzzel.blocked-dates.store'), [
                'date'   => $date->toDateTimeString(),
                'reason' => $reason,
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(PuzzelBlockedDate::class, [
            'reason' => $reason,
            'date'   => $date->toDateTimeString(),
        ]);

        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.puzzel.blocked-dates.store'), [
                'date' => $dateNoReason->toDateTimeString(),
            ])
            ->assertNoContent();

        $this->assertDatabaseHas(PuzzelBlockedDate::class, [
            'date' => $dateNoReason->toDateTimeString(),
        ]);
    }

    #[Test]
    public function storeInvalidDate(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.puzzel.blocked-dates.store'), [
                'date' => 'invalid-date',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    #[Test]
    public function storeInvalidDateToday(): void
    {
        $this->actingAsEmployee()
            ->postJson($this->generateRoute('admin.puzzel.blocked-dates.store'), [
                'date' => CarbonImmutable::yesterday()->toDateTimeString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }
}
