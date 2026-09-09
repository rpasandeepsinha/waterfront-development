<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Interfaces;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Invoices\Models\Invoice;

interface CommunicatesWithHarbor
{
    public function propagateInvoice(Invoice $invoice): void;

    public function propagateCustomer(Customer $customer): void;

    public function amqpConnection(): AMQPStreamConnection;

    public function receiveMessage(AMQPMessage $message): void;
}
