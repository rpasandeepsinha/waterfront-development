<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Database\UuidCast;

/**
 * @mixin Builder<DnsRecordChange>
 *
 * @property int              $id
 * @property Subscription     $subscription
 * @property string           $name
 * @property DnsRecordType    $record_type
 * @property DnsChangeType    $change_type
 * @property DnsAgentType     $agent_type
 * @property string           $content
 * @property int              $ttl
 * @property ?int             $priority
 * @property ?int             $weight
 * @property ?int             $port
 * @property int              $subscription_id
 * @property ?UuidInterface   $changed_by_uuid
 * @property ?string          $changed_by_metadata
 * @property string           $ip_address
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class DnsRecordChange extends Model
{
    use HasTimestamps;

    protected $table = 'dns_record_changes';

    protected $fillable = [
        'record_type',
        'change_type',
        'agent_type',
        'name',
        'content',
        'ttl',
        'priority',
        'weight',
        'port',
        'changed_by_uuid',
        'changed_by_metadata',
        'subscription_id',
        'ip_address',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    protected function casts(): array
    {
        return [
            'record_type' => DnsRecordType::class,
            'change_type' => DnsChangeType::class,
            'agent_type' => DnsAgentType::class,
            'changed_by_uuid' => UuidCast::class,
        ];
    }
}
