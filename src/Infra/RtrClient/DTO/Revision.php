<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\DTO;

use DateMalformedStringException;
use DateTime;
use RealtimeRegister\Domain\DomainDetails;
use RealtimeRegister\Domain\DomainObjectInterface;
use Waterfront\Infra\RtrClient\Enums\RevisionType;

class Revision implements DomainObjectInterface
{
    public function __construct(
        public DateTime $date,
        public ?int $processId,
        public int $revision,
        public RevisionType $type,
        public DomainDetails $entity
    ) {
    }

    /**
     * @throws DateMalformedStringException
     *
     * @phpstan-ignore-next-line
     */
    public static function fromArray(array $json): Revision
    {
        return new self(
            new DateTime($json['date']),
            $json['processId'] ?? null,
            $json['revision'],
            RevisionType::from($json['type']),
            DomainDetails::fromArray($json['entity'])
        );
    }

    /**
     * @return array{
     *     date: non-falsy-string,
     *     processId: int|null,
     *     revision: int,
     *     type: 'ADD'|'DEL'|'MOD',
     *     entity: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'date'              => $this->date->format('Y-m-d\TH:i:s\Z'),
            'processId'         => $this->processId,
            'revision'          => $this->revision,
            'type'              => $this->type->value,
            'entity'            => $this->entity->toArray(),
        ];
    }
}
