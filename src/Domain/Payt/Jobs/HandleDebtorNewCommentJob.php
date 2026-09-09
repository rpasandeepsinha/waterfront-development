<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payt\Jobs;

use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor;
use Waterfront\Domain\Payt\Services\PaytToPuzzelConvertor;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class HandleDebtorNewCommentJob extends AbstractQueueableJob
{
    public function __construct(
        public readonly PaytDebtor $debtor,
        public readonly PaytSupportedBusinessUnit $businessUnit,
    ) {
        parent::__construct();
    }

    public function handle(PaytToPuzzelConvertor $convertor): void
    {
        $convertor->createPuzzelTicketFromPaytDebtor(
            $this->debtor,
            $this->businessUnit,
        );
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::INVOICES;
    }
}
