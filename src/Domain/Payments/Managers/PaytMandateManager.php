<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Managers;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Payments\Exceptions\PaytMandateReferenceNullException;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateResponseDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMandateCreateDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMandateResponseDTO;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateIdNotNumericException;
use Waterfront\Infra\PaytClient\PaytMandateClient;
use Webmozart\Assert\Assert;

class PaytMandateManager
{
    public function __construct(
        private readonly PaytMandateClient $paytMandateClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PaytMandateApiException|InvalidArgumentException
     */
    public function findOrCreateMandate(Mandate $mandate, MollieMandateResponseDTO $mollieMandateResponse): PaytMandateResponseDTO
    {
        $existingPaytMandate = $this->findExistingPspMandate($mandate);

        if ($existingPaytMandate !== null) {
            $this->syncExistingPspMandate($mandate, $existingPaytMandate);

            return $existingPaytMandate;
        }

        if ($mollieMandateResponse->details->consumerName === null || $mollieMandateResponse->details->consumerAccount === null) {
            throw new InvalidArgumentException('Mollie mandate details are incomplete for creating Payt mandate.');
        }

        Assert::string($mandate->mollie_mandate_reference_id);

        $pspMandateCreateDTO = new PaytMandateCreateDTO(
            bankAccountName: $mollieMandateResponse->details->consumerName,
            bankAccountNumber: $mollieMandateResponse->details->consumerAccount,
            mandateIdentifier: $mandate->mollie_mandate_reference_id,
            debtorCode: (string) $mandate->mollieCustomer->customer->customer_number,
            customerIdentifier: $mandate->mollieCustomer->mollie_customer_reference_id,
        );

        $pspMandate = $this->paytMandateClient->createPspMandate($pspMandateCreateDTO);

        $mandate->payt_mandate_reference_id = $pspMandate->id;
        $mandate->save();

        return $pspMandate;
    }

    /**
     * @throws PaytMandateReferenceNullException
     * @throws PaytMandateIdNotNumericException
     * @throws PaytMandateApiException
     */
    public function getMandate(Mandate $mandate): PaytMandateResponseDTO|null
    {
        if ($mandate->payt_mandate_reference_id === null) {
            throw new PaytMandateReferenceNullException($mandate);
        }

        return $this->paytMandateClient->getPspMandatesByPaytId($mandate->payt_mandate_reference_id);
    }

    /**
     * @throws PaytMandateApiException
     */
    private function findExistingPspMandate(Mandate $mandate): PaytMandateResponseDTO|null
    {
        $pspMandates = $this->paytMandateClient->getPspMandatesByDebtorNumber(
            (string) $mandate->mollieCustomer->customer->customer_number,
        );

        foreach ($pspMandates as $pspMandate) {
            if ($pspMandate->mandateIdentifier === $mandate->mollie_mandate_reference_id) {
                return $pspMandate;
            }
        }

        return null;
    }

    private function syncExistingPspMandate(Mandate $mandate, PaytMandateResponseDTO $existingPaytMandate): void
    {
        if ($mandate->payt_mandate_reference_id === $existingPaytMandate->id) {
            return;
        }

        $this->logger->notice(sprintf(
            'Found Payt mandate "%s", but no reference to it exists in the database, updating Mandate entry "%d".',
            $existingPaytMandate->id,
            $mandate->id,
        ));

        $mandate->payt_mandate_reference_id = $existingPaytMandate->id;
        $mandate->save();
    }
}
