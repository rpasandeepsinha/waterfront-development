<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<ContactDTO>
 */
readonly class ContactSetDTO implements Countable, IteratorAggregate
{
    /**
     * @var ContactDTO[]
     */
    private array $contacts;

    public function __construct(ContactDTO ...$contacts)
    {
        $this->contacts = $contacts;
    }

    /**
     * @return Iterator<ContactDTO>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->contacts);
    }

    public function count(): int
    {
        return count($this->contacts);
    }

    /**
     * @param array<int,string> $contacts
     */
    public static function fromArray(array $contacts): self
    {
        $contactSet = [];
        /** @var array<string,string> $contact */
        foreach ($contacts as $contact) {
            $contactSet[] = ContactDTO::fromArray($contact);
        }

        return new ContactSetDTO(...$contactSet);
    }
}
