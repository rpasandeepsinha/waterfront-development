<?php

declare(strict_types=1);

namespace Tests\Domain\Payt\Jobs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor;
use Waterfront\Domain\Payt\Jobs\HandleDebtorNewCommentJob;
use Waterfront\Domain\Payt\Services\PaytToPuzzelConvertor;
use Waterfront\Infra\PaytClient\DTO\PaytDebtorDTO;
use Waterfront\Infra\PaytClient\DTO\PaytMessageDTO;
use Waterfront\Infra\PaytClient\Enums\PaytSupportedBusinessUnit;
use Waterfront\Infra\PaytClient\Factory\PaytClientFactory;
use Waterfront\Infra\PaytClient\PaytClient;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelCreateTicketRequest;
use Waterfront\Infra\PuzzelClient\DTO\PuzzelTicketCustomer;
use Waterfront\Infra\PuzzelClient\PuzzelPublicClient;

#[CoversClass(HandleDebtorNewCommentJob::class)]
#[CoversMethod(PaytToPuzzelConvertor::class, 'createPuzzelTicketFromPaytDebtor')]
class HandleDebtorNewCommentJobTest extends IntegrationTestCase
{
    #[Test]
    public function jobWillSucceedWithoutCreatingATicketForCreditor(): void
    {
        $paytDebtor = new PaytDebtor(
            resourceType: null,
            id: 2002,
            companyName: null,
            name: null,
            debtorCode: '3001244',
            administration: null,
        );

        $job = new HandleDebtorNewCommentJob(
            $paytDebtor,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $lastPaytMessageDto = new PaytMessageDTO(
            id: '123',
            senderType: 'creditor',
            content: 'content bla',
            sentAt: '2026-04-04T14:54:28.519681Z',
            receivedAt: null,
            subject: 'subject',
            creditCaseId: null,
        );

        $paytDebtorDto = new PaytDebtorDTO(
            id: '2002',
            debtorNumber: '3001244',
            name: 'john doe',
            callPhoneNumber: null,
            smsPhoneNumber: null,
            primaryEmailAddress: 'first@email.com',
            invoiceEmailAddress: null,
            debtorIdentifier: null,
            languageCode: null,
            postalAddress: null,
            administrationId: null,
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByDebtorId')
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
        $paytDebtor = new PaytDebtor(
            resourceType: null,
            id: 2002,
            companyName: null,
            name: null,
            debtorCode: '3001244',
            administration: null,
        );

        $job = new HandleDebtorNewCommentJob(
            $paytDebtor,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $lastPaytMessageDto = new PaytMessageDTO(
            id: '123',
            senderType: 'debtor',
            content: 'content bla',
            sentAt: '2026-04-04T14:54:28.519681Z',
            receivedAt: null,
            subject: 'subject',
            creditCaseId: null,
        );

        $paytDebtorDto = new PaytDebtorDTO(
            id: '2002',
            debtorNumber: '3001244',
            name: 'john doe',
            callPhoneNumber: null,
            smsPhoneNumber: null,
            primaryEmailAddress: 'first@email.com',
            invoiceEmailAddress: null,
            debtorIdentifier: null,
            languageCode: null,
            postalAddress: null,
            administrationId: null,
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByDebtorId')
            ->willReturn($lastPaytMessageDto);

        $paytClientFactoryMock = self::createMock(PaytClientFactory::class);
        $paytClientFactoryMock
            ->expects(self::atLeastOnce())
            ->method('create')
            ->with(PaytSupportedBusinessUnit::VERSIO2)
            ->willReturn($paytClientMock);

        $puzzelPublicClient = self::createMock(PuzzelPublicClient::class);
        $puzzelPublicClient
            ->expects(self::once())
            ->method('createTicket')
            ->with(self::equalTo(
                new PuzzelCreateTicketRequest(
                    subject: 'Reactie op debiteur 3001244',
                    body: 'content bla'
                    . '<br>The following email addresses are known in Payt to related debtor:<br>'
                    . 'Primary email address: first@email.com<br>',
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
                ),
            ));
        $convertor = new PaytToPuzzelConvertor(
            $puzzelPublicClient,
            self::createStub(LoggerInterface::class),
            $paytClientFactoryMock,
        );

        $job->handle($convertor);
    }
}
