<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Interfaces;

use Waterfront\Domain\Transfers\Models\Transfer;

interface ExecuteTransferInterface
{
    public function execute(Transfer $transfer): Transfer;
}
