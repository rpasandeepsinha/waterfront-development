<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

/**
 * @property int                               $id
 * @property ?string                           $mollie_mandate_reference_id
 * @property ?string                           $payt_mandate_reference_id
 * @property MollieMandateMethod               $method
 * @property CarbonImmutable                   $signature_date              "Y-m-d" in the database
 * @property int                               $mollie_customer_id
 * @property ?CarbonImmutable                  $updated_at
 * @property ?CarbonImmutable                  $created_at
 * @property MollieCustomer                    $mollieCustomer
 * @property Collection<int, MigratedCustomer> $migratedCustomers
 * @property ?CarbonImmutable                  $deleted_at
 *
 * @mixin Builder<Mandate>
 */
class Mandate extends Model implements AuditableContract
{
    use Auditable;
    use HasTimestamps;
    use SoftDeletes;

    protected $attributes = [
        'method' => MollieMandateMethod::class,
    ];

    /**
     * @return BelongsTo<MollieCustomer, $this>
     */
    public function mollieCustomer(): BelongsTo
    {
        return $this->belongsTo(MollieCustomer::class);
    }

    /**
     * @return BelongsToMany<MigratedCustomer, $this>
     */
    public function migratedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(MigratedCustomer::class)->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'method' => MollieMandateMethod::class,
            'signature_date' => 'date:Y-m-d',
        ];
    }
}
