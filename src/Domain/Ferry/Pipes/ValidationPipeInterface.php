<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Pipes;

use Closure;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;

interface ValidationPipeInterface
{
    public function handle(ValidationPayload $payload, Closure $next): ValidationPayload;

    public function getValidationIdentifier(): MigrationValidationPipes;
}
