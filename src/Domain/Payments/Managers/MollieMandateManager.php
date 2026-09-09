<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Managers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Payments\Models\Mandate;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateCreateInterface;
use Waterfront\Infra\MollieClient\DTO\Mandates\MollieMandateResponseDTO;
use Waterfront\Infra\MollieClient\Enums\MollieMandateMethod;
use Waterfront\Infra\MollieClient\Enums\MollieMandateStatus;
use Waterfront\Infra\MollieClient\Exceptions\MollieMandateApiException;
use Waterfront\Infra\MollieClient\MollieMandateClient;
use Webmozart\Assert\Assert;

class MollieMandateManager
{
    public function __construct(
        private readonly MollieMandateClient $mollieMandateClient,
        private readonly LoggerInterface $logger,
        private readonly MandateReferenceGenerator $mandateReferenceGenerator,
    ) {
    }

    /**
     * @throws MollieMandateApiException
     */
    public function findOrCreateMandate(
        MollieCustomer $mollieCustomer,
        MollieMandateCreateInterface $mollieMandateCreateDTO
    ): MollieMandateResponseDTO {
        $existingValidMandate = $this->findAndSyncValidMandate(
            $mollieCustomer,
            $mollieMandateCreateDTO->getMethod(),
            $mollieMandateCreateDTO->getIdentifyingValue(),
        );

        if ($existingValidMandate instanceof MollieMandateResponseDTO) {
            return $existingValidMandate;
        }

        $mandate = new Mandate();
        $mandate->method = $mollieMandateCreateDTO->getMethod();
        $mandate->signature_date = new CarbonImmutable($mollieMandateCreateDTO->getSignatureDate());

        $mollieCustomer->mandates()->save($mandate);

        $mandateReference = $this->mandateReferenceGenerator->generateMandateReference($mollieCustomer, $mandate);

        $mollieMandateCreateDTO->setMandateReference($mandateReference);

        $this->logger->info(
            sprintf(
                'Creating Mollie mandate for Mollie customer "%s" and DB mandate id "%d", mandate reference "%s"',
                $mollieCustomer->mollie_customer_reference_id,
                $mandate->id,
                $mandateReference,
            ),
        );

        try {
            // Create new mandate externally
            $externalMollieMandate = $this->mollieMandateClient->createMandate(
                $mollieCustomer->mollie_customer_reference_id,
                $mollieMandateCreateDTO
            );
        } catch (MollieMandateApiException $exception) {
            $mandate->forceDelete();

            throw $exception;
        }

        // Update the mandate we just created with the external data
        $mandate->mollie_mandate_reference_id = $externalMollieMandate->id;
        $mandate->signature_date = new CarbonImmutable($externalMollieMandate->signatureDate);
        $mandate->save();

        return $externalMollieMandate;
    }

    /**
     * @throws MollieMandateApiException
     */
    public function getMandate(MollieCustomer $mollieCustomer, Mandate $mandate): MollieMandateResponseDTO
    {
        Assert::string($mandate->mollie_mandate_reference_id);

        return $this->mollieMandateClient->getMandate(
            $mollieCustomer->mollie_customer_reference_id,
            $mandate->mollie_mandate_reference_id
        );
    }

    /**
     * @throws MollieMandateApiException
     *
     * @return array<MollieMandateResponseDTO>
     */
    public function listMandates(MollieCustomer $mollieCustomer): array
    {
        return $this->mollieMandateClient->listMandates($mollieCustomer->mollie_customer_reference_id);
    }

    /**
     * @param string $identifiableValue Searchable value of mandate details, IBAN or PayPal e-mail etc.
     *
     * @throws MollieMandateApiException
     */
    private function findAndSyncValidMandate(MollieCustomer $mollieCustomer, MollieMandateMethod $method, string $identifiableValue): MollieMandateResponseDTO|null
    {
        $mandates = $this->mollieMandateClient->listMandates($mollieCustomer->mollie_customer_reference_id);

        $existingValidMandate = $this->filterValidMandate($mandates, $method, $identifiableValue);

        if ($existingValidMandate instanceof MollieMandateResponseDTO) {
            $dbEntryExists = $mollieCustomer->mandates()
                ->where('mollie_mandate_reference_id', $existingValidMandate->id)
                ->exists();

            if (! $dbEntryExists) {
                $this->logger->notice(sprintf(
                    'Found Mollie mandate %s, but no reference to it exist in the database, creating new entry.',
                    $existingValidMandate->id,
                ));

                $mandate = new Mandate();
                $mandate->method = $existingValidMandate->method;
                $mandate->mollie_mandate_reference_id = $existingValidMandate->id;
                $mandate->signature_date = new CarbonImmutable($existingValidMandate->signatureDate);

                $mollieCustomer->mandates()->save($mandate);
            }

            return $existingValidMandate;
        }

        return null;
    }

    /**
     * @param array<int, MollieMandateResponseDTO> $mandates
     */
    private function filterValidMandate(array $mandates, MollieMandateMethod $method, string $identifiableValue): MollieMandateResponseDTO|null
    {
        return new Collection($mandates)
            ->first(function (MollieMandateResponseDTO $mandate) use ($method, $identifiableValue) {
                if (
                    $mandate->status === MollieMandateStatus::VALID &&
                    $mandate->method === $method &&
                    $mandate->details->consumerAccount === $identifiableValue
                ) {
                    return $mandate;
                }

                return null;
            });
    }
}
