<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * @property int              $id
 * @property string           $subscription_uuid
 * @property ?int             $certificate_id
 * @property ?int             $request_id
 * @property int              $provider_id
 * @property Subscription     $subscription
 * @property Provider         $provider
 * @property ?CarbonImmutable $last_result_received
 * @property ?string          $last_result
 * @property bool             $has_reissued
 * @property bool             $custom_csr
 * @property ?string          $webhook_request
 * @property ?string          $status
 * @property ?CarbonImmutable $webhook_request_received
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 * @property ?CarbonImmutable $expire_date
 *
 * @mixin Builder<SslDeployment>
 */
class SslDeployment extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'ssl_deployments';

    protected $fillable = [
        'subscription_uuid',
        'certificate_id',
        'request_id',
        'provider_id',
        'custom_csr',
        'has_reissued',
        'webhook_request',
        'webhook_request_received',
        'last_result',
        'last_result_received',
        'expire_date',
    ];

    public function getDnsRecordAttribute(): bool
    {
        $result = $this->last_result;
        if ($result !== null) {
            $decoded = json_decode($result, true, 512);
            if (! is_array($decoded)) {
                return false;
            }

            return (bool) Arr::get($decoded, 'dns_record', false);
        }

        return false;
    }

    public function getStatusAttribute(): ?string
    {
        $result = $this->last_result;
        if ($result !== null) {
            $decoded = json_decode($result, true, 512);
            if (! is_array($decoded)) {
                return 'UNKNOWN';
            }

            $status = Arr::get($decoded, 'certificate_status', 'NOT_FOUND');
            assert(is_string($status));

            return $status;
        }

        return 'UNKNOWN';
    }

    public function getHasCertificatesAttribute(): bool
    {
        $sanity = $this->subscription->sslSanity;
        if ($sanity !== null) {
            return (bool) $sanity->has_ssl_certificate_files;
        }

        return false;
    }

    public function getHasPrivateAttribute(): bool
    {
        $sanity = $this->subscription->sslSanity;
        if ($sanity !== null) {
            return (bool) $sanity->has_ssl_private_files;
        }

        return false;
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_uuid', 'uuid');
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** @return array<string,array<string>> */
    public static function namespaceWith(): array
    {
        return [
            self::class => ['subscription.product.productGroup'],
        ];
    }

    protected function casts(): array
    {
        return [
            'provider_id' => 'int',
            'custom_csr' => 'bool',
            'has_reissued' => 'bool',
            'certificate_id' => 'int',
            'request_id' => 'int',
            'webhook_request_received' => 'datetime',
            'last_result_received' => 'datetime',
            'expire_date' => 'datetime',
        ];
    }
}
