<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Exceptions;

use Saloon\Exceptions\SaloonException;

// @phpstan-ignore sandwave.custom
abstract class CaddyException extends SaloonException
{
}
