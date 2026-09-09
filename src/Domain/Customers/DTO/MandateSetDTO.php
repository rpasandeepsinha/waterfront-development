<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\DTO;

use ArrayIterator;
use Countable;
use Iterator;
use IteratorAggregate;
use Waterfront\Domain\Customers\Exceptions\MandateTypeNotSupportedException;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateCreateInterface;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;

/**
 * @implements IteratorAggregate<MollieMandateCreateInterface>
 */
readonly class MandateSetDTO implements Countable, IteratorAggregate
{
    /**
     * @var MollieMandateCreateInterface[]
     */
    private array $mandates;

    public function __construct(MollieMandateCreateInterface ...$mandates)
    {
        $this->mandates = $mandates;
    }

    /**
     * @return Iterator<MollieMandateCreateInterface>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->mandates);
    }

    public function count(): int
    {
        return count($this->mandates);
    }

    /**
     * @param array<int, array<string, string>> $mandates
     *
     * @throws MandateTypeNotSupportedException
     */
    public static function fromArray(array $mandates): self
    {
        $mandateSet = [];

        /** @var array<string,string> $mandate */
        foreach ($mandates as $mandate) {
            $mandateSet[] = match ($mandate['type']) {
                MollieMandateMethod::DIRECTDEBIT->value => new MollieMandateDirectDebitCreateDTO(
                    consumerName: $mandate['consumer_name'],
                    consumerAccount: $mandate['consumer_account'],
                    signatureDate: $mandate['signature_date'],
                    consumerBic: $mandate['consumer_bic'],
                ),
                default => throw new MandateTypeNotSupportedException($mandate['type']),
            };
        }

        return new self(...$mandateSet);
    }
}
