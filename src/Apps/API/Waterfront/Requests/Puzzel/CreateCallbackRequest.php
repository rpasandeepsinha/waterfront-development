<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Puzzel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Puzzel\Rules\CustomerCanAccessCallback;
use Waterfront\Domain\Puzzel\Rules\DateIsNotBlocked;
use Waterfront\Domain\Puzzel\Rules\NoFutureScheduledCalls;
use Waterfront\Domain\Puzzel\Rules\TimeslotExistsWithCapacity;

class CreateCallbackRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                Rule::date()->format('Y-m-d'),
                $this->container->make(DateIsNotBlocked::class),
            ],
            'timeSlotUuid' => [
                'required',
                'exists:puzzel_callback_timeslots,uuid',
                $this->container->make(TimeslotExistsWithCapacity::class),
                $this->container->make(NoFutureScheduledCalls::class),
                $this->container->make(CustomerCanAccessCallback::class),
            ],
            'category' => 'nullable|string',
            'description' => 'nullable|string',
        ];
    }
}
