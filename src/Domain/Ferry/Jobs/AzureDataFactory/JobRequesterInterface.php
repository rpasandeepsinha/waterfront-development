<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs\AzureDataFactory;

interface JobRequesterInterface
{
    public function getMessageType(): string;
}
