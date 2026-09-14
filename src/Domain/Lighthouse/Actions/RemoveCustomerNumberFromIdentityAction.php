<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Actions;

use JsonException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Lighthouse\Exceptions\DetachCustomerNumberFromIdentityFailedException;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;

class RemoveCustomerNumberFromIdentityAction
{
    public function __construct(
        private readonly LighthouseApiService $lighthouseApiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DetachCustomerNumberFromIdentityFailedException
     * @throws JsonException
     */
    public function execute(UuidInterface $uuid, int $customerNumber): void
    {
        try {
            $this->logger->info(
                sprintf(
                    'Detaching customer number %d from lighthouse identity %s',
                    $customerNumber,
                    $uuid,
                ),
            );
            $this->lighthouseApiService->detachIdentityForBusinessUnit($uuid, $customerNumber);
        } catch (LighthouseException $exception) {
            $message = sprintf(
                'Failed to detach customer number %d from lighthouse identity %s',
                $customerNumber,
                $uuid,
            );
            $this->logger->error($message);

            throw new DetachCustomerNumberFromIdentityFailedException(
                $message,
                $exception->getCode(),
                $exception,
            );
        }
    }
}
