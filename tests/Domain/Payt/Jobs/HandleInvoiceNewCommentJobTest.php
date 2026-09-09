<?php

declare(strict_types=1);

namespace Tests\Domain\Payt\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytAdministration as PaytAdministrationWebhookDTO;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor as PaytDebtorWebhookDTO;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoice as PaytInvoiceWebhookDTO;
use Waterfront\Domain\Payt\Jobs\HandleInvoiceNewCommentJob;
use Waterfront\Domain\Payt\Services\PaytToPuzzelConvertor;
use Waterfront\Infra\PaytClient\DTO\PaytDebtorDTO as PaytDebtorApiDTO;
use Waterfront\Infra\PaytClient\DTO\PaytDebtorPostalAddressDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMessageDTO as PaytMessageApiDTO;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Waterfront\Infra\PaytClient\Factory\PaytClientFactory;
use Waterfront\Infra\PaytClient\PaytClient;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelCreateTicketRequest;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelTicketCustomer;
use Waterfront\Infra\PuzzelClient\PuzzelPublicClient;

#[CoversClass(HandleInvoiceNewCommentJob::class)]
#[CoversMethod(PaytToPuzzelConvertor::class, 'createPuzzelTicketFromPaytInvoice')]
class HandleInvoiceNewCommentJobTest extends IntegrationTestCase
{
    #[Test]
    public function jobWillSucceedWithoutCreatingATicketForCreditor(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244'
        );
        $paytInvoice = $this->createPaytInvoiceWebhookDTO(
            resourceType: 'invoice',
            id: 1001,
            invoiceNumber: '10012356',
            debtor: $paytDebtor,
        );

        $job = new HandleInvoiceNewCommentJob(
            $paytInvoice,
            PaytSupportedBusinessUnit::VERSIO2
        );

        $lastPaytMessageDto = $this->createPaytMessageApiDTO(
            id: '123',
            senderType: 'creditor',
            content: 'content bla',
            sentAt: '2026-04-04T14:54:28.519681Z',
            subject: 'subject',
        );

        $paytDebtorDto = $this->createPaytDebtorApiDTO(
            id: '456',
            debtorNumber: '3001244',
            name: 'john doe',
            primaryEmailAddress: 'first@email.com',
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getDebtorByDebtorNumber')
            ->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByInvoiceId')
            ->willReturn($lastPaytMessageDto);

        $paytClientFactoryMock = self::createMock(PaytClientFactory::class);
        $paytClientFactoryMock
            ->expects(self::atLeastOnce())
            ->method('create')
            ->with(PaytSupportedBusinessUnit::VERSIO2)
            ->willReturn($paytClientMock);

        $puzzelPublicClient = self::createMock(PuzzelPublicClient::class);
        $puzzelPublicClient->expects(self::never())->method('createTicket');
        $convertor = new PaytToPuzzelConvertor(
            $puzzelPublicClient,
            self::createStub(LoggerInterface::class),
            $paytClientFactoryMock,
        );

        $job->handle($convertor);
    }

    #[Test]
    public function jobWillSucceedToCreateTicket(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244'
        );
        $paytInvoice = $this->createPaytInvoiceWebhookDTO(
            resourceType: 'invoice',
            id: 1001,
            invoiceNumber: '10012356',
            debtor: $paytDebtor,
        );

        $job = new HandleInvoiceNewCommentJob(
            $paytInvoice,
            PaytSupportedBusinessUnit::VERSIO2
        );

        $lastPaytMessageDto = $this->createPaytMessageApiDTO(
            id: '123',
            senderType: 'debtor',
            content: 'content bla',
            sentAt: '2026-04-04T14:54:28.519681Z',
            subject: 'subject',
        );

        $paytDebtorDto = $this->createPaytDebtorApiDTO(
            id: '456',
            debtorNumber: '3001244',
            name: 'john doe',
            primaryEmailAddress: 'first@email.com',
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getDebtorByDebtorNumber')
            ->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByInvoiceId')
            ->willReturn($lastPaytMessageDto);

        $paytClientFactoryMock = self::createMock(PaytClientFactory::class);
        $paytClientFactoryMock
            ->expects(self::atLeastOnce())
            ->method('create')
            ->with(PaytSupportedBusinessUnit::VERSIO2)
            ->willReturn($paytClientMock);

        $puzzelPublicClient = self::createMock(PuzzelPublicClient::class);
        $puzzelPublicClient->expects(self::once())
            ->method('createTicket')
            ->with(self::equalTo(
                new PuzzelCreateTicketRequest(
                    subject: 'Reactie op factuur 10012356',
                    body: 'content bla' .
                    '<br>The following email addresses are known in Payt to related debtor:<br>' .
                    'Primary email address: first@email.com<br>',
                    customer: new PuzzelTicketCustomer(
                        email: $paytDebtorDto->primaryEmailAddress ?? '',
                        firstName: $paytDebtorDto->name,
                        phoneNumber: $paytDebtorDto->callPhoneNumber,
                    ),
                    team: PuzzelCreateTicketRequest::TEAM_CS_ADMIN,
                    categories: [
                        ['name' => 'Brand', 'value' => 'Versio 2.0'],
                        ['name' => 'Customer ID', 'value' => $paytDebtorDto->debtorNumber],
                    ],
                )
            ));
        $convertor = new PaytToPuzzelConvertor(
            $puzzelPublicClient,
            self::createStub(LoggerInterface::class),
            $paytClientFactoryMock,
        );

        $job->handle($convertor);
    }

    private function createPaytDebtorApiDTO(
        string $id,
        string $debtorNumber,
        string $name,
        string|null $callPhoneNumber = null,
        string|null $smsPhoneNumber = null,
        string|null $primaryEmailAddress = null,
        string|null $invoiceEmailAddress = null,
        string|null $debtorIdentifier = null,
        string|null $languageCode = null,
        PaytDebtorPostalAddressDTO|null $postalAddress = null,
        string|null $administrationId = null,
    ): PaytDebtorApiDTO {
        return new PaytDebtorApiDTO(
            id: $id,
            debtorNumber: $debtorNumber,
            name: $name,
            callPhoneNumber: $callPhoneNumber,
            smsPhoneNumber: $smsPhoneNumber,
            primaryEmailAddress: $primaryEmailAddress,
            invoiceEmailAddress: $invoiceEmailAddress,
            debtorIdentifier: $debtorIdentifier,
            languageCode: $languageCode,
            postalAddress: $postalAddress,
            administrationId: $administrationId
        );
    }

    private function createPaytMessageApiDTO(
        string $id,
        string $senderType,
        string $content,
        string|null $sentAt = null,
        string|null $receivedAt = null,
        string|null $subject = null,
        string|null $creditCaseId = null,
    ): PaytMessageApiDTO {
        return new PaytMessageApiDTO(
            id: $id,
            senderType: $senderType,
            content: $content,
            sentAt: $sentAt,
            receivedAt: $receivedAt,
            subject: $subject,
            creditCaseId: $creditCaseId
        );
    }

    private function createPaytDebtorWebhookDTO(
        int $id,
        string|null $resourceType = null,
        string|null $companyName = null,
        string|null $name = null,
        string|null $debtorCode = null,
        PaytAdministrationWebhookDTO|null $administration = null,
    ): PaytDebtorWebhookDTO {
        return new PaytDebtorWebhookDTO(
            resourceType: $resourceType,
            id: $id,
            companyName: $companyName,
            name: $name,
            debtorCode: $debtorCode,
            administration: $administration,
        );
    }

    private function createPaytInvoiceWebhookDTO(
        string|null $resourceType,
        int $id,
        string|null $invoiceNumber = null,
        string|null $invoiceDate = null,
        string|null $dueDate = null,
        string|null $amountTotal = null,
        string|null $amountOpen = null,
        string|null $currencyCode = null,
        string|null $orderNumber = null,
        PaytDebtorWebhookDTO|null $debtor = null,
        PaytAdministrationWebhookDTO|null $administration = null,
    ): PaytInvoiceWebhookDTO {
        return new PaytInvoiceWebhookDTO(
            resourceType: $resourceType,
            id: $id,
            invoiceNumber: $invoiceNumber,
            invoiceDate: $invoiceDate,
            dueDate: $dueDate,
            amountTotal: $amountTotal,
            amountOpen: $amountOpen,
            currencyCode: $currencyCode,
            orderNumber: $orderNumber,
            debtor: $debtor,
            administration: $administration
        );
    }
}
