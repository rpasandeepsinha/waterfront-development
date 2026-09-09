<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\Providers\Models\ProviderSetting;

/**
 * @extends Factory<ProviderSetting>
 */
class ProviderSettingsFactory extends Factory
{
    protected $model = ProviderSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->slug(),
            'value' => $this->faker->text(),
        ];
    }
}
