<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Pipelines\CreateCertificate;

use RuntimeException;
use Throwable;
use Waterfront\Domain\Ssl\Interfaces\Models\Parameters as CreateParameters;
use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Infra\OpenproviderClient\Factories\OpenproviderClientFactory;

class CreateCertificateStep
{
    public function __construct(
        private readonly OpenproviderClientFactory $openproviderClientFactory,
    ) {
    }

    public function execute(CreateParameters $parameters): Result
    {
        try {
            return $this->openproviderClientFactory->create()->createSsl($parameters);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Failed to create SSL deployment: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception,
            );
        }
    }
}
