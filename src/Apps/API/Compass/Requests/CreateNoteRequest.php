<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property ?string $subscription_uuid
 * @property string  $note
 */
class CreateNoteRequest extends FormRequest
{
    /** @return array<string> */
    public function rules(): array
    {
        return [
            'note' => 'required|string',
            'subscription_uuid' => 'sometimes|required|exists:subscriptions,uuid',
        ];
    }
}
