<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Transformers;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Domain\DNS\Hydrators\DnsRecordHydrator;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Models\DnsCustomerTemplateRecord;
use Waterfront\Domain\DNS\Services\DnsRecordConverter;

/**
 * @property DnsRecordInterface|null $resource
 */
class DnsRecordResource extends Resource
{
    public ?string $redirectUuid = null;

    /**
     * @param Request $request
     *
     * @return mixed[]
     */
    public function toArray($request): array
    {
        $converter = Container::getInstance()->make(DnsRecordConverter::class);

        if ($this->resource instanceof DnsCustomerTemplateRecord) {
            $this->resource = $converter->transformToTypedRecord($this->resource, '@');
        }

        if (is_null($this->resource)) {
            return [];
        }

        $hydrator = Container::getInstance()->make(DnsRecordHydrator::class);

        return array_merge(
            ['redirect_uuid' => $this->redirectUuid],
            $hydrator->dehydrate($this->resource),
        );
    }
}
