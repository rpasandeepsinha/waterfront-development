<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient;

use Countable;
use Iterator;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Mapper\Mapper;

/**
 * @implements Iterator<int, mixed>
 */
class CloudStackPaginationIterator implements Iterator, Countable
{
    private const int DEFAULT_PAGE_SIZE = 500;

    private ?int $count = null;

    private int $position = 0;

    /** @var array<array<mixed>> */
    private array $pages = [];

    /**
     * @template T
     *
     * @param array<string, string|bool> $parameters
     * @param Mapper<T>                  $mapper
     */
    public function __construct(
        private readonly CloudStackBaseClient $client,
        private readonly string $command,
        private readonly array $parameters,
        private readonly string $type,
        private readonly Mapper $mapper,
        private readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    /**
     * @throws ClientException
     */
    public function current(): mixed
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->pages[(int) ($this->position / $this->pageSize)][$this->position % $this->pageSize];
    }

    public function next(): void
    {
        $this->position++;
    }

    public function key(): int
    {
        return $this->position;
    }

    /**
     * @throws ClientException
     */
    public function valid(): bool
    {
        $this->fetchPage((int) ($this->position / $this->pageSize));

        return $this->position < $this->count;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @throws ClientException
     */
    public function count(): int
    {
        $this->fetchPage(0);

        return (int) $this->count;
    }

    /**
     * @throws ClientException
     */
    private function fetchPage(int $pageNumber): void
    {
        if ($this->count !== null && ($pageNumber * $this->pageSize) >= $this->count) {
            return;
        }

        if (! array_key_exists($pageNumber, $this->pages)) {
            $parameters = $this->parameters;
            $parameters['page'] = (string) ($pageNumber + 1); // CloudStack starts counting at 1
            $parameters['pagesize'] = (string) $this->pageSize;

            $response = $this->client->execute($this->command, $parameters);

            // Empty response
            if (! array_key_exists('count', $response)) {
                $this->count = 0;

                return;
            }

            if ($this->count !== $response['count']) {
                $this->count = $response['count'];
                $this->pages = [];
            }

            $this->pages[$pageNumber] = array_map($this->mapper, $response[$this->type]);
        }
    }
}
