<?php

declare(strict_types=1);

namespace Waterfront\Domain\Email\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Waterfront\Domain\Email\Enums\ReceiverType;

/**
 * @property int              $id
 * @property string           $uuid
 * @property ?string          $receiver_email
 * @property ?int             $template_id
 * @property ReceiverType     $receiver_type
 * @property string           $receiver_uuid
 * @property ?string          $cc_emails
 * @property CarbonImmutable  $sent_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?string          $payload
 * @property ?string          $hubspot_status
 * @property ?string          $hubspot_id
 * @property ?CarbonImmutable $requested_at
 * @property Template         $template
 * @property ?string          $last_result
 *
 * @mixin Builder<EmailHistory>
 */
class EmailHistory extends Model
{
    protected $table = 'email_history';

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class)->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'receiver_type' => ReceiverType::class,
            'requested_at' => 'datetime',
        ];
    }
}
