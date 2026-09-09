<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Repository;

use Illuminate\Database\Eloquent\Collection;
use JsonException;
use Waterfront\Domain\DNS\DTO\DnsRecordChangeDTO;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Models\DnsRecordChange;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Authentication\Helpers\PermissionsHelper;

class DnsRecordChangeRepository
{
    /**
     * @throws JsonException
     */
    public function createDnsRecordChange(
        DnsRecordChangeDTO $dnsRecordChangeDTO,
        DnsAgentType $dnsAgentType,
        Subscription $subscription,
        string $ip_address,
        AuthenticatedCustomer|AuthenticatedEmployee|null $authenticatedSubject = null,
    ): void {
        $changedByMetadata = [];

        if ($authenticatedSubject !== null) {
            $changedByMetadata = [
                'email' => $authenticatedSubject->identitySchema->traits?->email,
                'schemaId' => PermissionsHelper::getKratosSchemaId($authenticatedSubject),
            ];
        }

        DnsRecordChange::create([
            'record_type' => $dnsRecordChangeDTO->record_type,
            'change_type' => $dnsRecordChangeDTO->change_type,
            'agent_type' => $dnsAgentType,
            'name' => $dnsRecordChangeDTO->name,
            'content' => $dnsRecordChangeDTO->content,
            'ttl' => $dnsRecordChangeDTO->ttl,
            'priority' => $dnsRecordChangeDTO->priority,
            'weight' => $dnsRecordChangeDTO->weight,
            'port' => $dnsRecordChangeDTO->port,
            'changed_by_uuid' => $authenticatedSubject?->identitySchema->id,
            'changed_by_metadata' => json_encode($changedByMetadata, JSON_THROW_ON_ERROR),
            'subscription_id' => $subscription->id,
            'ip_address' => $ip_address,
        ]);
    }

    /**
     * @return Collection<int, DnsRecordChange>
     */
    public function getBySubscription(Subscription $subscription, int $limit): Collection
    {
        return DnsRecordChange::query()
            ->where('subscription_id', $subscription->id)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
