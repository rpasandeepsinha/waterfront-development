<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Interfaces;

use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Infra\CloudStackClient\CloudStackClient;

interface AdminClientFactoryInterface
{
    /**
     * @throws AdminClientFactoryException
     */
    public function create(Environment $environment): CloudStackClient;
}
