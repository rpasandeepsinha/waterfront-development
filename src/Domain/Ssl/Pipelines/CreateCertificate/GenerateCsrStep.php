<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Pipelines\CreateCertificate;

use RuntimeException;
use Throwable;
use Waterfront\Domain\Ssl\Services\CsrManager;

class GenerateCsrStep
{
    public function __construct(
        private readonly CsrManager $csrManager,
    ) {
    }

    /**
     * @param mixed[] $customerData
     *
     * @throws RuntimeException
     */
    public function execute(array $customerData, string $sslDomain): string
    {
        try {
            $this->csrManager->create($customerData, $sslDomain);

            return $this->csrManager->getRawCsr($sslDomain);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to generate CSR while creating an SSL deployment: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }
}
