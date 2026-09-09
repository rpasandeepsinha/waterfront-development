<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\Servers\Enums\ServerType;

class ImportHostingServersRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'server_type' => [
                'required',
                Rule::enum(ServerType::class)->only([ServerType::DIRECTADMIN, ServerType::PLESK]),
            ],
            'csv_upload' => [
                'required',
                'file',
                'extensions:csv',
                'mimetypes:text/csv,text/plain,application/csv',
                'max:1024',
            ],
        ];
    }
}
