<?php

declare(strict_types=1);

namespace Tests\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;

/**
 * @extends Factory<SslDeployment>
 */
class SslDeploymentFactory extends Factory
{
    protected $model = SslDeployment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $result = Result::create([
            'status' => Arr::random(['error', 'ok']),
            'certificateId' => random_int(1, 10000),
            'certificateStatus' => Arr::random([DomainStatus::REQUESTED->value, DomainStatus::ACTIVE->value, DomainStatus::FAILED->value]),
            'requestId' => random_int(1, 10000),
            'csr' => 'csr',
        ]);

        return [
            'subscription_uuid' => Uuid::uuid4(),
            'certificate_id' => $result->getCertificateId(),
            'last_result' => json_encode($result->toArray(), JSON_THROW_ON_ERROR),
            'last_result_received' => CarbonImmutable::now(),
            'expire_date' => CarbonImmutable::now()->addMonths(6),
            'request_id' => $result->getRequestId(),
        ];
    }

    public function rtrProvider(): self
    {
        return $this->state(fn () => [
            'provider_id' => ProviderFactory::new()->sslRtr(),
        ]);
    }

    public function openProviderProvider(): self
    {
        return $this->state(fn () => [
            'provider_id' => ProviderFactory::new()->sslOpenProvider(),
        ]);
    }
}
