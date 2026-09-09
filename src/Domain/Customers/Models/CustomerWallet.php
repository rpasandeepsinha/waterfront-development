<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A wallet belongs to one Versio customer, reflecting the wallet they had in their legacy system.
 * The customer can use this for requesting a refund of their wallet, and nothing else.
 * https://yh-jira.atlassian.net/browse/HARBOR-573.
 *
 * @property int              $id
 * @property int              $customer_id
 * @property Customer         $customer
 * @property int              $amount
 * @property ?string          $bank_account_number
 * @property ?string          $bank_account_name
 * @property ?CarbonImmutable $refund_requested_at
 * @property ?CarbonImmutable $csv_downloaded_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<CustomerWallet>
 */
class CustomerWallet extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'customer_id',
        'amount',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'refund_requested_at' => 'datetime',
            'csv_downloaded_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'customer_id' => 'int',
            'amount' => 'int',
        ];
    }
}
