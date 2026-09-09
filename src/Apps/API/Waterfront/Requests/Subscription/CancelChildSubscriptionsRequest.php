<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int    $amount
 * @property string $parent
 */
class CancelChildSubscriptionsRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $subscriptionUuid = (string) $this->request->get('parent');

        return [
            'parent' => [
                'required',
                Rule::exists(
                    'subscriptions',
                    'uuid'
                )->where(
                    'uuid',
                    $subscriptionUuid
                ),
            ],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}
