<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Actions;

use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Managers\MollieCustomerManager;
use Waterfront\Domain\Payments\Managers\MollieMandateManager;
use Waterfront\Domain\Payments\Managers\PaytMandateManager;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerMetadataDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateDirectDebitCreateDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Support\Enums\LoggingContextKeys;

class CreateDirectDebitMandateAction
{
    public function __construct(
        private readonly MollieCustomerManager $mollieCustomerManager,
        private readonly MollieMandateManager $mollieMandateManager,
        private readonly PaytMandateManager $paytMandateManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws MollieMandateApiException
     * @throws MollieCustomerApiException
     * @throws PaytMandateApiException
     */
    public function execute(
        Customer $customer,
        string $consumerName,
        string $consumerAccount, // IBAN
        CarbonImmutable $signatureDate, // Y-m-d will be used
        ?string $consumerBic = null,
    ): Mandate {
        $this->logger->debug(sprintf('Creating mandate for customer %s', $customer->customer_number), [
            LoggingContextKeys::CUSTOMER_ID => $customer->id,
        ]);

        $mollieCustomerRequestDTO = new MollieCustomerRequestDTO(
            $customer->name,
            $customer->email,
            $customer->locale,
            new MollieCustomerMetadataDTO(debtorId: $customer->customer_number),
        );

        $mollieCustomerResponseDTO = $this->mollieCustomerManager->findOrCreate(
            $mollieCustomerRequestDTO,
            $customer,
        );

        $this->logger->debug(sprintf('Using Mollie customer %s for mandate', $mollieCustomerResponseDTO->id), [
            LoggingContextKeys::CUSTOMER_ID => $customer->id,
        ]);

        /** @var MollieCustomer $mollieCustomer */
        $mollieCustomer = $customer
            ->mollieCustomer()
            ->where('mollie_customer_reference_id', $mollieCustomerResponseDTO->id)
            ->firstOrFail();

        $mollieMandateCreateDTO = new MollieMandateDirectDebitCreateDTO(
            consumerName: $consumerName,
            consumerAccount: $consumerAccount,
            signatureDate: $signatureDate->toDateString(),
            consumerBic: $consumerBic,
        );

        $mollieMandateResponseDTO = $this->mollieMandateManager->findOrCreateMandate(
            $mollieCustomer,
            $mollieMandateCreateDTO,
        );

        $this->logger->debug(
            sprintf(
                'Using Mollie customer %s mandate %s',
                $mollieCustomerResponseDTO->id,
                $mollieMandateResponseDTO->id,
            ),
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
            ],
        );

        /** @var Mandate $mandate */
        $mandate = $mollieCustomer
            ->mandates()
            ->where('mollie_mandate_reference_id', $mollieMandateResponseDTO->id)
            ->firstOrFail();

        $this->paytMandateManager->findOrCreateMandate($mandate, $mollieMandateResponseDTO);

        $mandate->refresh();

        $customer->has_direct_debit = true;
        $customer->save();

        return $mandate;
    }
}
