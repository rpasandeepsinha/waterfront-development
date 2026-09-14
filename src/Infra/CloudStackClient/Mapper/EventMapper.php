<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

use DateTimeImmutable;
use Waterfront\Infra\CloudStackClient\DTO\Event;

/**
 * @implements Mapper<Event>
 */
class EventMapper implements Mapper
{
    public function __invoke(array $data): Event
    {
        assert(is_string($data['id']));
        assert(is_string($data['account']));
        assert(is_string($data['domainid']));
        assert(is_string($data['type']));
        assert(is_string($data['description']));
        assert(is_string($data['state']));
        assert(is_string($data['level']));
        assert(is_string($data['created']));

        return new Event(
            id: $data['id'],
            account: $data['account'],
            domainId: $data['domainid'],
            type: $data['type'],
            description: $data['description'],
            state: $data['state'],
            level: $data['level'],
            created: new DateTimeImmutable($data['created']),
        );
    }
}
