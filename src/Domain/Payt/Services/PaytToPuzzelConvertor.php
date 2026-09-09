<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payt\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytCreditCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoice;
use Waterfront\Infra\PaytClient\DTO\PaytDebtorDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMessageDTO;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Waterfront\Infra\PaytClient\Factory\PaytClientFactory;
use Waterfront\Infra\PaytClient\PaytClient;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelCreateTicketRequest;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelTicketCustomer;
use Waterfront\Infra\PuzzelClient\PuzzelPublicClient;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class PaytToPuzzelConvertor
{
    public function __construct(
        private PuzzelPublicClient $client,
        private LoggerInterface $logger,
        private PaytClientFactory $paytClientFactory
    ) {
    }

    public function createPuzzelTicketFromPaytInvoice(PaytInvoice $paytInvoice, PaytSupportedBusinessUnit $businessUnit): void
    {
        $debtorCode = $paytInvoice->debtor?->debtorCode;
        Assert::notNull($debtorCode);

        $paytClient = $this->getPaytClient($businessUnit);
        $paytDebtorDto = $this->getPaytDebtor($debtorCode, $paytClient);

        $message = $paytClient->getLastMessageByInvoiceId((string) $paytInvoice->id);
        Assert::notNull($message, 'expected to have a last message retrieved from Payt');

        if ($message->senderType !== PaytMessageDTO::SENDER_TYPE_DEBTOR) {
            // only create ticket for incoming messages from the debtor.
            return;
        }

        $this->logger->info('Creating Puzzel ticket for comment on invoice', [
            LoggingContextKeys::META => [
                'invoice_id' => $paytInvoice->id,
                'business_unit' => $businessUnit->value,
                'debtor_code' => $debtorCode,
            ],
        ]);

        $this->createPuzzelTicket(
            paytMessage:  $message,
            paytDebtor: $paytDebtorDto,
            businessUnit: $businessUnit,
            team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
            subject: sprintf('Reactie op factuur %s', $paytInvoice->invoiceNumber),
        );
    }

    public function createPuzzelTicketFromPaytCreditCase(PaytCreditCase $paytCreditCase, PaytSupportedBusinessUnit $businessUnit): void
    {
        $debtorCode = $paytCreditCase->debtor?->debtorCode;
        Assert::notNull($debtorCode, 'Debtor code should not be empty');

        $paytClient = $this->getPaytClient($businessUnit);
        $paytDebtorDto = $this->getPaytDebtor($debtorCode, $paytClient);

        $message = $paytClient->getLastMessageByCreditCaseId((string) $paytCreditCase->id);
        Assert::notNull($message, 'expected to have a last message retrieved from Payt');

        if ($message->senderType !== PaytMessageDTO::SENDER_TYPE_DEBTOR) {
            // only create ticket for incoming messages from the debtor.
            return;
        }

        $this->createPuzzelTicket(
            paytMessage: $message,
            paytDebtor: $paytDebtorDto,
            businessUnit: $businessUnit,
            team: PuzzelCreateTicketRequest::TEAM_CREDIT_MANAGEMENT,
            subject: sprintf('Reactie op incassozaak %s', $paytCreditCase->creditCaseNumber),
        );

        $this->logger->info('Creating Puzzel ticket for comment on credit case', [
            LoggingContextKeys::META => [
                'credit_case_id' => $paytCreditCase->id,
                'business_unit' => $businessUnit->value,
                'debtor_code' => $debtorCode,
            ],
        ]);
    }

    public function createPuzzelTicketFromPaytDebtor(PaytDebtor $paytDebtor, PaytSupportedBusinessUnit $businessUnit): void
    {
        $debtorCode = $paytDebtor->debtorCode;
        Assert::notNull($debtorCode);

        $paytClient = $this->getPaytClient($businessUnit);
        $paytDebtorDto = $this->getPaytDebtor($debtorCode, $paytClient);

        $message = $paytClient->getLastMessageByDebtorId((string) $paytDebtor->id);
        Assert::notNull($message, 'expected to have a last message retrieved from Payt');

        if ($message->senderType !== PaytMessageDTO::SENDER_TYPE_DEBTOR) {
            // only create ticket for incoming messages from the debtor.
            return;
        }

        $this->createPuzzelTicket(
            paytMessage: $message,
            paytDebtor: $paytDebtorDto,
            businessUnit: $businessUnit,
            team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
            subject: sprintf('Reactie op debiteur %s', $debtorCode),
        );

        $this->logger->info('Creating Puzzel ticket for comment on debtor', [
            LoggingContextKeys::META => [
                'debtor_id' => $paytDebtor->id,
                'business_unit' => $businessUnit->value,
                'debtor_code' => $debtorCode,
            ],
        ]);
    }

    private function getPaytClient(PaytSupportedBusinessUnit $businessUnit): PaytClient
    {
        return $this->paytClientFactory->create($businessUnit);
    }

    private function getPaytDebtor(string $debtorcode, PaytClient $paytClient): PaytDebtorDTO
    {
        $debtors = $paytClient->getDebtorByDebtorNumber($debtorcode);

        if (sizeof($debtors) !== 1) {
            throw new RuntimeException(sprintf(
                'Unable create ticket in Puzzel, unexpected amount of debtors (%d) received from Payt',
                sizeof($debtors)
            ));
        }

        return $debtors[0];
    }

    private function getBodySupplementedWithEmailAddresses(PaytMessageDTO $paytMessageDTO, PaytDebtorDTO $paytDebtorDTO): string
    {
        $string = $paytMessageDTO->content;
        $string .= '<br>The following email addresses are known in Payt to related debtor:<br>';
        if ($paytDebtorDTO->primaryEmailAddress !== null) {
            $string .= 'Primary email address: ' . $paytDebtorDTO->primaryEmailAddress . '<br>';
        }
        if ($paytDebtorDTO->invoiceEmailAddress !== null) {
            $string .= 'Invoice email address: ' . $paytDebtorDTO->invoiceEmailAddress . '<br>';
        }
        return $string;
    }

    private function getPuzzelTicketCustomerDTO(PaytDebtorDTO $paytDebtorDTO): PuzzelTicketCustomer
    {
        if ($paytDebtorDTO->primaryEmailAddress !== null) {
            $emailField = $paytDebtorDTO->primaryEmailAddress;
        } elseif ($paytDebtorDTO->invoiceEmailAddress !== null) {
            $emailField = $paytDebtorDTO->invoiceEmailAddress;
        } else {
            throw new RuntimeException('Unable create ticket in Puzzel, no e-mail address found PaytDebtor');
        }

        $emailAddresses = explode(',', $emailField);

        return new PuzzelTicketCustomer(
            email: $emailAddresses[0],
            firstName: $paytDebtorDTO->name,
            phoneNumber: $paytDebtorDTO->callPhoneNumber,
        );
    }

    private function createPuzzelTicket(
        PaytMessageDTO $paytMessage,
        PaytDebtorDTO $paytDebtor,
        PaytSupportedBusinessUnit $businessUnit,
        string $team,
        string $subject
    ): void {
        $brand = $this->getPuzzelBrandForBusinessUnit($businessUnit);
        $categories = [
            ['name' => 'Brand', 'value' => $brand],
            ['name' => 'Customer ID', 'value' => $paytDebtor->debtorNumber],
        ];
        $puzzelTicket = new PuzzelCreateTicketRequest(
            subject: $subject,
            body: $this->getBodySupplementedWithEmailAddresses($paytMessage, $paytDebtor),
            customer: $this->getPuzzelTicketCustomerDTO($paytDebtor),
            team: $team,
            categories: $categories,
        );

        $this->client->createTicket($puzzelTicket);
    }

    private function getPuzzelBrandForBusinessUnit(PaytSupportedBusinessUnit $businessUnit): string
    {
        return match ($businessUnit) {
            PaytSupportedBusinessUnit::DEHEEG => 'De Heeg',
            PaytSupportedBusinessUnit::NEOSTRADA => 'Neostrada',
            PaytSupportedBusinessUnit::REALHOSTING => 'Realhosting',
            PaytSupportedBusinessUnit::SOHOSTED => 'Sohosted',
            PaytSupportedBusinessUnit::VERSIO2 => 'Versio 2.0',
            PaytSupportedBusinessUnit::YOURHOSTING2 => 'Yourhosting 2.0',
        };
    }
}
