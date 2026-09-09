<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\EventListener;

use JsonException;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Entity\Customer as KpnCustomer;
use SandwaveIo\Office365\Exception\Office365Exception;
use SandwaveIo\Office365\Library\Observer\Customer\CustomerObserverInterface;
use SandwaveIo\Office365\Library\Observer\Status\Status;
use Waterfront\Domain\Customers\Models\Customer as WaterfrontCustomer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365ProcessStatus;
use Waterfront\Domain\Microsoft365\Exceptions\TenantNameTakenException;
use Waterfront\Domain\Microsoft365\Mailer\Microsoft365SignMca;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class CustomerCreateListener implements CustomerObserverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * @throws JsonException
     * @throws TenantNameTakenException
     * @throws Office365Exception
     */
    public function execute(KpnCustomer $kpnCustomer, ?Status $status): void
    {
        $partnerReference = $kpnCustomer->getHeader()?->getPartnerReference();
        $kpnCustomerNumber = (string) $kpnCustomer->getCustomerId();

        if ($partnerReference === null) {
            $this->logger->error(sprintf(
                'No partnerReference found for kpn customer id: [%s]',
                $kpnCustomerNumber
            ));
            return;
        }

        $explodedString = explode('-', str_replace('WF-CUSTOMER-', '', $partnerReference));
        $waterfrontCustomerId = $explodedString[0];
        $customerInfoId = $explodedString[1];

        $this->logger->info(
            sprintf(
                "Received KPN customer created webhook call for Waterfront customer id '%d' and KPN customer number '%s'",
                $waterfrontCustomerId,
                $kpnCustomerNumber
            )
        );

        $waterfrontCustomer = WaterfrontCustomer::where('id', $waterfrontCustomerId)->firstOrFail();
        $customerInfo = Microsoft365CustomerInfo::where('customer_id', $waterfrontCustomer->customer()->id)
            ->where('id', $customerInfoId)->firstOrFail();
        $customerInfo->kpn_customer_id = $kpnCustomerNumber;
        $customerInfo->technical_status = Microsoft365ProcessStatus::CUSTOMER_CREATED;
        $customerInfo->save();

        $this->sendMcaEmail($customerInfo->customer);
    }

    private function sendMcaEmail(WaterfrontCustomer $customer): void
    {
        $customerPanelBaseUrl = $this->configuration->getAsString('app.url');
        $coastMicrosoftUrl = sprintf('%s/microsoft-365', $customerPanelBaseUrl);

        $this->mailer->send(
            [$customer],
            new Microsoft365SignMca(
                $coastMicrosoftUrl,
            )
        );
    }
}
