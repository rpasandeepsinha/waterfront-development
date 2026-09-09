<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Action;

use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Services\Enums\LogStatus;

class ParseRtrTransferStatusToWfStatusAction
{
    public function execute(string $status): string
    {
        $result = '';
        switch ($status) {
            case LogStatus::STATUS_PENDING:
            case LogStatus::STATUS_PENDINGVALIDATION:
            case LogStatus::STATUS_PENDING_APPROVAL_AUTHORIZED_CONTACT:
            case LogStatus::STATUS_PENDINGWHOIS:
                $result =  TechnicalStatus::PENDING->value;
                break;
            case LogStatus::STATUS_FAILED:
            case LogStatus::STATUS_REJECTED:
            case LogStatus::STATUS_CANCELLED:
                $result =  TechnicalStatus::FAILED->value;
                break;
            case LogStatus::STATUS_APPROVED:
            case LogStatus::STATUS_COMPLETED:
                $result = TechnicalStatus::OK->value;
        }
        return $result;
    }
}
