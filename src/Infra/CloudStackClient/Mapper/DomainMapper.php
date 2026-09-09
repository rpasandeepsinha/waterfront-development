<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use Waterfront\Infra\CloudStackClient\DTO\Domain;

/**
 * @implements Mapper<Domain>
 */
class DomainMapper implements Mapper
{
    public function __invoke(array $data): Domain
    {
        assert(is_string($data['id']));
        assert(is_string($data['name']));
        assert(is_string($data['parentdomainid']));

        return new Domain(
            id:             $data['id'],
            name:           $data['name'],
            parentDomainId: $data['parentdomainid'],
        );
    }
}
