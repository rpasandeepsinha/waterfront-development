<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;

/**
 * @extends Factory<Microsoft365SyncLog>
 */
class Microsoft365SyncLogFactory extends Factory
{
    protected $model = Microsoft365SyncLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log' => $this->faker->text(100),
        ];
    }
}
