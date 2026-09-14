<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\VPS\Models\CloudstackJob;

/**
 * @extends Factory<CloudstackJob>
 */
class CloudstackJobFactory extends Factory
{
    protected $model = CloudstackJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => $this->faker->uuid(),
            'user_id' => $this->faker->uuid(),
            'cmd' => 'org.apache.cloudstack.api.command.user.vm.DeployVMCmd',
            'status' => 0,
            'proc_status' => 0,
            'result_code' => 0,
            'instance_type' => 'VirtualMachine',
            'instance_id' => $this->faker->uuid(),
            'cloudstack_created' => CarbonImmutable::now()->format(DateTime::ISO8601),
            'job_id' => $this->faker->uuid(),
        ];
    }
}
