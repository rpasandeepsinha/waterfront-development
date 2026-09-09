<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

use Waterfront\Domain\Provision\Enums\ProvisionProvider;

interface ProvisionServiceInterface
{
    public function getDefaultProvider(): ProvisionProvider;

    /**
     * @return ProvisionResultInterface|null null if validation passed, otherwise a ProvisionResultInterface with status VALIDATION_ERROR
     */
    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface;

    /**
     * Send the given provisioning requests. Assumes the request to already have been validated.
     */
    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface;
}
