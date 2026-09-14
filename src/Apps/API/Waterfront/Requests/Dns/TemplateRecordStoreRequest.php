<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;

class TemplateRecordStoreRequest extends FormRequest
{
    /**
     * TODO: This does not validate in context, this needs to be fixed.
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        /** @var DnsRecordsValidationService $service */
        $service = $this->container->make(DnsRecordsValidationService::class);

        return $service->getRecordRules(strval($this->string('type', '')), []);
    }
}
