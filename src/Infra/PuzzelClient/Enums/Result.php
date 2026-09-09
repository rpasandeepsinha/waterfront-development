<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Enums;

enum Result: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
}
