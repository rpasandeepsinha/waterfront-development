<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Managers;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Payments\Exceptions\PaytMandateStillExistsException;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\MollieMandateClient;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateApiException;
use Waterfront\Infra\PaytClient\Exceptions\PaytMandateIdNotNumericException;
use Waterfront\Infra\PaytClient\PaytMandateClient;
use Webmozart\Assert\Assert;

class MandateRevokeManager
{
    public function __construct(
        private readonly MollieMandateClient $mollieMandateClient,
        private readonly PaytMandateClient $paytMandateClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws MollieMandateApiException
     * @throws PaytMandateIdNotNumericException
     * @throws PaytMandateApiException
     * @throws PaytMandateStillExistsException
     */
    public function revokeMandate(Mandate $mandate): void
    {
        $this->logger->debug(
            sprintf(
                'Attempting to revoke mandate with ID {%d}',
                $mandate->id,
            ),
        );

        /*
         * When you revoke a mandate at Mollie the revoke isn't synched to Payt.
         * But when you revoke at Payt the revoke is synched to Mollie.
         * And the only way to revoke at Payt is to do it manually in their control panel.
         * Our reference id doesn't get emptied when someone revokes the mandate at Payt.
         * So the mandate at Payt shouldn't exist anymore if we call this function.
         * If it never existed to begin with, we can just continue to revoke the mandate at Mollie.
         */
        if ($mandate->payt_mandate_reference_id !== null) {
            $remoteMandate = $this->paytMandateClient->getPspMandatesByPaytId($mandate->payt_mandate_reference_id);

            if ($remoteMandate !== null) {
                throw new PaytMandateStillExistsException($mandate);
            }
        }

        Assert::string($mandate->mollie_mandate_reference_id);

        $this->logger->debug(
            sprintf(
                'Mandate with ID {%d} and Mollie mandate {%s} and Payt reference ID {%s} is already revoked at Payt, proceeding',
                $mandate->id,
                $mandate->mollie_mandate_reference_id,
                $mandate->payt_mandate_reference_id,
            ),
        );

        try {
            $this->mollieMandateClient->revokeMandate(
                $mandate->mollieCustomer->mollie_customer_reference_id,
                $mandate->mollie_mandate_reference_id,
            );

            $this->logger->debug(
                sprintf(
                    'Mandate with ID {%d} revoked mandate {%s} at Mollie',
                    $mandate->id,
                    $mandate->mollie_mandate_reference_id,
                ),
            );
        } catch (MollieMandateApiException $mollieMandateApiException) {
            if ($mollieMandateApiException->status !== Response::HTTP_GONE) {
                throw $mollieMandateApiException;
            }

            $this->logger->debug(
                sprintf(
                    'Mandate with ID {%d} is already revoked at Mollie, proceeding',
                    $mandate->id,
                ),
            );
        }

        $this->logger->debug(
            sprintf(
                'Deleting Mandate with ID {%d} from database',
                $mandate->id,
            ),
        );

        $mandate->delete();
    }
}
