<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Actions;

use Psr\Log\LoggerInterface;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use Waterfront\Domain\Lighthouse\Exceptions\FailedToFetchIdentitiesForCustomerNumbers;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;

class GetIdentitiesForCustomerNumberAction
{
    public function __construct(
        private readonly LighthouseApiService $lighthouseApiService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws FailedToFetchIdentitiesForCustomerNumbers
     *
     * @return Identity[]
     */
    public function execute(int $customerNumber): array
    {
        try {
            return $this->lighthouseApiService->getKratosIdentitiesByCustomerNumber($customerNumber);
        } catch (LighthouseException $exception) {
            $message = sprintf(
                'Failed to fetch lighthouse identities for customer number %d',
                $customerNumber,
            );
            $this->logger->error($message);

            throw new FailedToFetchIdentitiesForCustomerNumbers(
                $message,
                $exception->getCode(),
                $exception,
            );
        }
    }
}
