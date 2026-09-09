<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Notes\Models\Notes;

/**
 * @extends Factory<Notes>
 */
class NotesFactory extends Factory
{
    protected $model = Notes::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'note' => $this->faker->text('200'),
        ];
    }
}
