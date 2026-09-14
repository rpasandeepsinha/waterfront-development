<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Enums\TransferStatus;

/**
 * Represents a subscription transfer defined from customer to customer.
 *
 * @mixin Model
 * @mixin Builder<Transfer>
 *
 * @property int                           $id
 * @property string                        $uuid
 * @property int                           $from_customer_id
 * @property int                           $to_customer_id
 * @property Customer                      $fromCustomer
 * @property Customer                      $toCustomer
 * @property Collection<int, Subscription> $subscriptions
 * @property ?CarbonImmutable              $created_at
 * @property ?CarbonImmutable              $updated_at
 * @property ?CarbonImmutable              $rejected_at
 * @property ?CarbonImmutable              $canceled_at
 * @property ?CarbonImmutable              $accepted_at
 * @property ?CarbonImmutable              $started_at
 * @property ?CarbonImmutable              $completed_at
 */
#[RouteKey('uuid')]
class Transfer extends Model
{
    protected $table = 'transfers';

    protected $fillable = [
        'uuid',
        'from_customer_id',
        'to_customer_id',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsToMany<Subscription, $this>
     */
    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(
            Subscription::class,
        )->withPivot(['executed_at', 'failed_at', 'reason_failed']);
    }

    public function getStatus(): TransferStatus
    {
        if ($this->isFailed()) {
            return TransferStatus::FAILED;
        } elseif ($this->isCompleted()) {
            return TransferStatus::COMPLETED;
        } elseif ($this->isCanceled()) {
            return TransferStatus::CANCELED;
        } elseif ($this->isRejected()) {
            return TransferStatus::REJECTED;
        } elseif ($this->isStarted()) {
            return TransferStatus::STARTED;
        } elseif ($this->isAccepted()) {
            return TransferStatus::ACCEPTED;
        }

        return TransferStatus::REQUESTED;
    }

    public function getTransferstatusAttribute(): string
    {
        return ucwords($this->getStatus()->value);
    }

    public function isOpen(): bool
    {
        return in_array(
            $this->getStatus(),
            [
                TransferStatus::STARTED,
                TransferStatus::ACCEPTED,
                TransferStatus::REQUESTED,
            ],
            true,
        );
    }

    public function complete(): bool
    {
        $status = $this->getStatus();

        if ($status === TransferStatus::STARTED) {
            $this->attributes['completed_at'] = CarbonImmutable::now();
            $this->save();

            return true;
        }

        return false;
    }

    public function accept(): bool
    {
        if ($this->getStatus() === TransferStatus::REQUESTED) {
            $this->attributes['accepted_at'] = CarbonImmutable::now();
            $this->save();

            return true;
        }

        return false;
    }

    public function reject(): bool
    {
        if ($this->getStatus() === TransferStatus::REQUESTED) {
            $this->attributes['rejected_at'] = CarbonImmutable::now();
            $this->save();

            return true;
        }

        return false;
    }

    public function cancel(): bool
    {
        if ($this->getStatus() === TransferStatus::REQUESTED) {
            $this->attributes['canceled_at'] = CarbonImmutable::now();
            $this->save();

            return true;
        }

        return false;
    }

    public function start(): bool
    {
        if ($this->getStatus() === TransferStatus::ACCEPTED) {
            $this->attributes['started_at'] = CarbonImmutable::now();
            $this->save();

            return true;
        }

        return false;
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(function (self $model): void {
            if ($model->uuid === null || $model->uuid === '') {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    protected function casts(): array
    {
        return [
            'id' => 'int',
            'from_customer_id' => 'int',
            'to_customer_id' => 'int',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'rejected_at' => 'datetime',
            'canceled_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    private function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    private function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    private function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    private function isStarted(): bool
    {
        return $this->started_at !== null;
    }

    private function isFailed(): bool
    {
        return $this->subscriptions()->wherePivotNotNull('failed_at')->exists() && $this->isCompleted();
    }
}
