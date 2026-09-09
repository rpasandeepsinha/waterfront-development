<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;

/**
 * @property ?bool  $manage_subscriptions
 * @property string $administrative_status
 * @property string $technical_status
 * @property ?int   $parent_subscription
 */
class ProcessLineItemRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'manage_subscriptions' => ['sometimes', 'boolean'],
            'administrative_status' => ['required', Rule::enum(AdministrativeStatus::class)],
            'technical_status' => ['required', Rule::enum(TechnicalStatus::class)],
            'parent_subscription' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
