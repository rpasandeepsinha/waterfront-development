<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\CloudStack;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\VPS\Enums\VirtualMachineState;

class StateRequest extends FormRequest
{
    /**
     * @return array<mixed>
     */
    public function rules(): array
    {
        return [
            'state' => [
                'required',
                Rule::in([
                    VirtualMachineState::START->value,
                    VirtualMachineState::STOP->value,
                    VirtualMachineState::REBOOT->value,
                ]),
            ],
        ];
    }
}
