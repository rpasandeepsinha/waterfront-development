<?php

declare(strict_types=1);

namespace Waterfront\Domain\Harbor\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Psr\Log\LoggerInterface;
use SandwaveIo\HarborMessages\Message\Serializer\JsonSerializer;
use Waterfront\Domain\Customers\Services\CustomerVatService;
use Waterfront\Domain\Harbor\Interfaces\CommunicatesWithHarbor;
use Waterfront\Domain\Harbor\Services\Harbor;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtCollectionStatusUpdatedHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\DebtorSsoUrlHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\InvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\MandateAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\Handler\WithdrawInvoicePaymentAnnouncementHandler;
use Waterfront\Domain\Harbor\Services\Message\MessageService;
use Waterfront\Domain\Invoices\Repositories\InvoiceRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Providers\BaseProvider;

class HarborServiceProvider extends BaseProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(CommunicatesWithHarbor::class, fn (): Harbor => new Harbor(
            new JsonSerializer(),
            $this->resolve(InvoiceRepository::class),
            $this->resolve(MessageService::class),
            $this->resolve(CustomerVatService::class),
            $this->resolve(ConfigurationInterface::class),
            $this->resolve(DebtorSsoUrlHandler::class),
            $this->resolve(InvoicePaymentAnnouncementHandler::class),
            $this->resolve(WithdrawInvoicePaymentAnnouncementHandler::class),
            $this->resolve(DebtCollectionStatusUpdatedHandler::class),
            $this->resolve(MandateAnnouncementHandler::class),
            $this->resolve(LoggerInterface::class),
        ));
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [CommunicatesWithHarbor::class];
    }
}
