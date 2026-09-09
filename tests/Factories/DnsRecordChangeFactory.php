<?php

declare(strict_types=1);

namespace Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Models\DnsRecordChange;

/**
 * @extends Factory<DnsRecordChange>
 */
class DnsRecordChangeFactory extends Factory
{
    protected $model = DnsRecordChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = $this->faker->domainName();

        $agentTypes = DnsAgentType::cases();
        $agentType = $agentTypes[random_int(0, count($agentTypes) - 1)];

        $changeTypes = DnsChangeType::cases();
        $changeType = $changeTypes[random_int(0, count($changeTypes) - 1)];

        $recordTypes = DnsRecordType::cases();
        $recordType = $recordTypes[random_int(0, count($recordTypes) - 1)];

        $content = match ($recordType) {
            DnsRecordType::AAAA => $this->faker->ipv6(),
            DnsRecordType::CAA, DnsRecordType::TXT, DnsRecordType::TLSA => $this->faker->text(),
            DnsRecordType::CNAME, DnsRecordType::MX, DnsRecordType::SRV => $domain,
            default => $this->faker->ipv4(),
        };

        $usesPriority = match ($recordType) {
            DnsRecordType::SRV, DnsRecordType::MX => true,
            default => false,
        };

        return [
            'record_type' => $recordType,
            'change_type' => $changeType,
            'agent_type' => $agentType,
            'name' => $domain,
            'content' => $content,
            'ttl' => $this->faker->numberBetween(3600, 86400),
            'priority' => $usesPriority ? $this->faker->numberBetween(0, 10) : null,
            'weight' => $recordType === DnsRecordType::SRV ? $this->faker->numberBetween(0, 10) : null,
            'port' => $recordType === DnsRecordType::SRV ? $this->faker->numberBetween(1, 65535) : null,
            'changed_by_uuid' => null,
            'subscription_id' => $this->faker->randomNumber(),
            'ip_address' => $this->faker->ipv6(),
        ];
    }
}
