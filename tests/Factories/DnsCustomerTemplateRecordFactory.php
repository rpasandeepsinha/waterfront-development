<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;

/**
 * @extends Factory<DnsCustomerTemplateRecord>
 */
class DnsCustomerTemplateRecordFactory extends Factory
{
    protected $model = DnsCustomerTemplateRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            DnsRecordType::A,
            DnsRecordType::AAAA,
            DnsRecordType::CAA,
            DnsRecordType::CNAME,
            DnsRecordType::MX,
            DnsRecordType::SRV,
            DnsRecordType::TLSA,
            DnsRecordType::TXT,
        ]);
        assert($type instanceof DnsRecordType);

        $content = match ($type) {
            DnsRecordType::A => '1.2.3.4',
            DnsRecordType::AAAA => '::1',
            DnsRecordType::CAA => '0 issue "ssl.test"',
            DnsRecordType::CNAME => 'alias.example.test',
            DnsRecordType::MX => 'mail-server.test',
            DnsRecordType::SRV => 'specific-service.test',
            DnsRecordType::TLSA => '3 1 1 randomhash',
            DnsRecordType::TXT => 'txt record',
            default => 'Please add example content for: ' . $type->value
        };

        $priority = null;
        $weight = null;
        $port = null;

        if (in_array($type, [DnsRecordType::MX, DnsRecordType::SRV], true)) {
            $priority = $this->faker->numberBetween(0, 30);
        }

        if ($type === DnsRecordType::SRV) {
            $weight = $this->faker->numberBetween(0, 30);
            $port = $this->faker->numberBetween(0, 30);
        }

        return [
            'name' => $this->faker->boolean() ? $this->faker->word() . '.@' : '@',
            'content' => $content,
            'type' => $type,
            'ttl' => $this->faker->numberBetween(900, 7200),
            'priority' => $priority,
            'weight' => $weight,
            'port' => $port,
            'disabled' => $this->faker->boolean(),
        ];
    }
}
