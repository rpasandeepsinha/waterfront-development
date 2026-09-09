<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int                           $id
 * @property string                        $title
 * @property string                        $subject
 * @property ?string                       $header
 * @property string                        $body
 * @property ?string                       $footer
 * @property string                        $slug
 * @property ?string                       $hubspot_template_id
 * @property Collection<int, EmailHistory> $emailHistory
 * @property ?CarbonImmutable              $deleted_at
 *
 * @mixin Builder<Template>
 */
class Template extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;

    protected $table = 'templates';

    /** @return HasMany<EmailHistory, $this> */
    public function EmailHistory(): HasMany
    {
        return $this->hasMany(EmailHistory::class);
    }
}
