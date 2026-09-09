<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource as Resource;
use Waterfront\Domain\Domains\Models\DomainContact;

/**
 * @property DomainContact $resource
 */
class DomainContactResource extends Resource
{
    /**
     * @param Request $request
     *
     * @return mixed[]
     */
    public function toArray($request): array
    {
        return [
            'id'                   => $this->resource->id,
            'uuid'                 => $this->resource->uuid,
            'email'                => $this->resource->email,
            'first_name'           => $this->resource->first_name,
            'last_name'            => $this->resource->last_name,
            'phone'                => $this->resource->getPhoneNumberAttribute(),
            'address'              => CustomerAddressResource::make($this->resource->getAddressAttribute()),
            'organization'         => $this->resource->organization,
            'default_owner'        => $this->resource->default_owner,
            'coupled_domains'      => $this->transformCoupledDomainSubscriptions(),
            'has_anonymous_handle' => $this->resource->has_anonymous_handle,
        ];
    }

    /**
     * @return mixed[]
     */
    private function transformCoupledDomainSubscriptions(): array
    {
        $result = [];
        $domainDeployments = $this->resource->contactOwnerDomainSubscriptions;

        if ($domainDeployments->count() > 0) {
            foreach ($domainDeployments as $domainDeployment) {
                $domain = $domainDeployment->subscription->domain;
                $result[] = [
                    'domain'  => $domain,
                    'private' => $domainDeployment->private_whois_enabled,
                    [
                        'type' => [
                            'owner' => $domainDeployment->contactOwner === null ? false : $domainDeployment->contactOwner->exists(),
                            'tech'  => true,
                            'admin' => true,
                        ],
                    ],
                ];
            }
        }

        return $result;
    }
}
