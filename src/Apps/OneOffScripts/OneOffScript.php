<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string           $slug
 * @property ?string          $ticket_ref
 * @property ?string          $output_last_run
 * @property ?CarbonImmutable $last_executed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<OneOffScript>
 */
class OneOffScript extends Model
{
    protected $table = 'one_off_scripts';

    protected $fillable = [
        'slug',
        'ticket_ref',
        'output_last_run',
        'last_executed_at',
    ];

    public function isExecuted(): bool
    {
        return $this->last_executed_at !== null;
    }

    protected function casts(): array
    {
        return [
            'last_executed_at' => 'datetime',
        ];
    }
}
