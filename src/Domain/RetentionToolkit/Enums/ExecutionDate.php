<?php

declare(strict_types=1);

namespace Waterfront\Domain\RetentionToolkit\Enums;

enum ExecutionDate: string
{
    case IMMEDIATE = 'immediate';
    case CONTRACT_END = 'contract_end';
}
