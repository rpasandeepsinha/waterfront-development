<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Plesk\Services;

use Waterfront\Domain\Hosting\Interfaces\Hosting\ClientInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\ClientListStrategyInterface;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\FirstComeFirstServeStrategy;
use Waterfront\Domain\Hosting\Plesk\Services\ClientList\OnlyOneInstanceShouldReturnTrueStrategy;

/**
 * Factory that creates in instance of ClientListStrategyInterface. This strategy determines right now by just
 * checking if we are in production of development mode.
 */
class ClientListStrategyFactory
{
    public function __construct(
        private readonly bool $devMode,
    ) {
    }

    /**
     * Creates Strategy class. Which class is used depends on whether you are in dev mode or not.
     *
     * @param ClientInterface[] $clients
     */
    public function create(iterable $clients, ?string $interfaceCheck = null): ClientListStrategyInterface
    {
        if ($this->devMode) {
            return new OnlyOneInstanceShouldReturnTrueStrategy($clients, $interfaceCheck);
        }

        return new FirstComeFirstServeStrategy($clients, $interfaceCheck);
    }
}
