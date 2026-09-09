<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<AddressDTO>
 */
readonly class AddressSetDTO implements Countable, IteratorAggregate
{
    /**
     * @var AddressDTO[]
     */
    private array $addresses;

    public function __construct(AddressDTO ...$addresses)
    {
        $this->addresses = $addresses;
    }

    /**
     * @return Iterator<AddressDTO>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->addresses);
    }

    public function count(): int
    {
        return count($this->addresses);
    }

    /**
     * @param array<int, array<string, string>> $addresses
     */
    public static function fromArray(array $addresses): self
    {
        $addressesSet = [];
        foreach ($addresses as $address) {
            $addressesSet[] = AddressDTO::fromArray($address);
        }

        return new AddressSetDTO(...$addressesSet);
    }
}
