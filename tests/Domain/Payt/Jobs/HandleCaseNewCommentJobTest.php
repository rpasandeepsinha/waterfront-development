<?php

declare(strict_types=1);

namespace Tests\Domain\Payt\Jobs;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytAdministration as PaytAdministrationWebhookDTO;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytCreditCase as PaytCreditCaseWebhookDTO;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytDebtor as PaytDebtorWebhookDTO;
use Waterfront\Apps\Webhooks\DTO\Payt\PaytInvoice;
use Waterfront\Domain\Payt\Jobs\HandleCaseNewCommentJob;
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
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(HandleCaseNewCommentJob::class)]
#[CoversMethod(PaytToPuzzelConvertor::class, 'createPuzzelTicketFromPaytCreditCase')]
class HandleCaseNewCommentJobTest extends IntegrationTestCase
{
    #[Test]
    public function jobWillFillByRuntimeErrorUnexpectedAmountOfDebtors(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244',
        );
        $paytCreditCase = $this->createPaytCreditCaseWebhookDTO(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            debtor: $paytDebtor,
        );

        $job = new HandleCaseNewCommentJob(
            $paytCreditCase,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([]);

        $paytClientFactoryMock = self::createMock(PaytClientFactory::class);
        $paytClientFactoryMock
            ->expects(self::atLeastOnce())
            ->method('create')
            ->with(PaytSupportedBusinessUnit::VERSIO2)
            ->willReturn($paytClientMock);

        $convertor = new PaytToPuzzelConvertor(
            self::createStub(PuzzelPublicClient::class),
            self::createStub(LoggerInterface::class),
            $paytClientFactoryMock,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs(
            'Unable create ticket in Puzzel, unexpected amount of debtors (0) received from Payt',
        );
        $job->handle($convertor);
    }

    #[Test]
    public function jobWillWillFailByAssertionErrorOnIncorrectDebtorCode(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(id: 2002);
        $paytCreditCase = $this->createPaytCreditCaseWebhookDTO(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            debtor: $paytDebtor,
        );

        $job = new HandleCaseNewCommentJob(
            $paytCreditCase,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $convertor = new PaytToPuzzelConvertor(
            self::createStub(PuzzelPublicClient::class),
            self::createStub(LoggerInterface::class),
            self::createStub(PaytClientFactory::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $job->handle($convertor);
    }

    #[Test]
    public function jobWillFailByAssertionErrorOnNoLastMessageRetrievedFromPayt(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244',
        );
        $paytCreditCase = $this->createPaytCreditCaseWebhookDTO(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            debtor: $paytDebtor,
        );

        $job = new HandleCaseNewCommentJob(
            $paytCreditCase,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $paytDebtorDto = $this->createPaytDebtorApiDTO(
            id: '456',
            debtorNumber: '3001244',
            name: 'john doe',
            primaryEmailAddress: 'first@email.com',
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([$paytDebtorDto]);
        $paytClientMock->expects(self::atLeastOnce())->method('getLastMessageByCreditCaseId')->willReturn(null);

        $paytClientFactoryMock = self::createMock(PaytClientFactory::class);
        $paytClientFactoryMock
            ->expects(self::atLeastOnce())
            ->method('create')
            ->with(PaytSupportedBusinessUnit::VERSIO2)
            ->willReturn($paytClientMock);

        $convertor = new PaytToPuzzelConvertor(
            self::createStub(PuzzelPublicClient::class),
            self::createStub(LoggerInterface::class),
            $paytClientFactoryMock,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(
            'expected to have a last message retrieved from Payt',
        );

        $job->handle($convertor);
    }

    #[Test]
    public function jobWillSucceedWithoutCreatingATicketForCreditor(): void
    {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244',
        );
        $paytCreditCase = $this->createPaytCreditCaseWebhookDTO(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            debtor: $paytDebtor,
        );

        $job = new HandleCaseNewCommentJob(
            $paytCreditCase,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $lastPaytMessageDto = $this->createPaytMessageApiDTO(
            id: '123',
            senderType: 'creditor',
            content: 'content bla',
            sentAt: 'subject bla',
        );

        $paytDebtorDto = $this->createPaytDebtorApiDTO(
            id: '456',
            debtorNumber: '3001244',
            name: 'john doe',
            primaryEmailAddress: 'first@email.com',
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByCreditCaseId')
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

    public static function providePaytDebtorEmailCases(): Generator
    {
        yield 'Multiple primary email addresses' => [
            'primaryEmailAddress' => 'first@email.com,second@email.com',
            'invoiceEmailAddress' => null,
            'expectedEmailAddress' => 'first@email.com',
            'expectedBody' =>
                'content bla'
                    . '<br>The following email addresses are known in Payt to related debtor:<br>'
                    . 'Primary email address: first@email.com,second@email.com<br>',
        ];
        yield 'no primary email addresses, multiple invoice email addresses' => [
            'primaryEmailAddress' => null,
            'invoiceEmailAddress' => 'first@email.com,second@email.com',
            'expectedEmailAddress' => 'first@email.com',
            'expectedBody' =>
                'content bla'
                    . '<br>The following email addresses are known in Payt to related debtor:<br>'
                    . 'Invoice email address: first@email.com,second@email.com<br>',
        ];
        yield 'Only a single invoice email addresses' => [
            'primaryEmailAddress' => null,
            'invoiceEmailAddress' => 'first@email.com',
            'expectedEmailAddress' => 'first@email.com',
            'expectedBody' =>
                'content bla'
                    . '<br>The following email addresses are known in Payt to related debtor:<br>'
                    . 'Invoice email address: first@email.com<br>',
        ];
        yield 'both primary email addresses, multiple invoice email addresses' => [
            'primaryEmailAddress' => 'third@email.com',
            'invoiceEmailAddress' => 'first@email.com,second@email.com',
            'expectedEmailAddress' => 'third@email.com',
            'expectedBody' =>
                'content bla'
                    . '<br>The following email addresses are known in Payt to related debtor:<br>'
                    . 'Primary email address: third@email.com<br>'
                    . 'Invoice email address: first@email.com,second@email.com<br>',
        ];
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[DataProvider('providePaytDebtorEmailCases')]
    public function jobWillSucceedToCreateTicketForCustomerWithMultipleEmailAddressesConfiguredInPayt(
        ?string $primaryEmailAddress,
        ?string $invoiceEmailAddress,
        string $expectedEmailAddress,
        string $expectedBody,
    ): void {
        $paytDebtor = $this->createPaytDebtorWebhookDTO(
            id: 2002,
            debtorCode: '3001244',
        );
        $paytCreditCase = $this->createPaytCreditCaseWebhookDTO(
            id: 2,
            resourceType: 'credit_case',
            creditCaseNumber: '123 456 789',
            debtor: $paytDebtor,
        );

        $job = new HandleCaseNewCommentJob(
            $paytCreditCase,
            PaytSupportedBusinessUnit::VERSIO2,
        );

        $lastPaytMessageDto = $this->createPaytMessageApiDTO(
            id: '123',
            senderType: 'debtor',
            content: 'content bla',
            sentAt: 'subject bla',
        );

        $paytDebtorDto = $this->createPaytDebtorApiDTO(
            id: '456',
            debtorNumber: '3001244',
            name: 'john doe',
            primaryEmailAddress: $primaryEmailAddress,
            invoiceEmailAddress: $invoiceEmailAddress,
        );

        $paytClientMock = $this->createMock(PaytClient::class);
        $paytClientMock->expects(self::atLeastOnce())->method('getDebtorByDebtorNumber')->willReturn([$paytDebtorDto]);
        $paytClientMock
            ->expects(self::atLeastOnce())
            ->method('getLastMessageByCreditCaseId')
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
                    subject: 'Reactie op incassozaak 123 456 789',
                    body: $expectedBody,
                    customer: new PuzzelTicketCustomer(
                        email: $expectedEmailAddress,
                        firstName: $paytDebtorDto->name,
                        phoneNumber: $paytDebtorDto->callPhoneNumber,
                    ),
                    team: PuzzelCreateTicketRequest::TEAM_CREDIT_MANAGEMENT,
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

    private function createPaytDebtorApiDTO(
        string $id,
        string $debtorNumber,
        string $name,
        ?string $callPhoneNumber = null,
        ?string $smsPhoneNumber = null,
        ?string $primaryEmailAddress = null,
        ?string $invoiceEmailAddress = null,
        ?string $debtorIdentifier = null,
        ?string $languageCode = null,
        ?PaytDebtorPostalAddressDTO $postalAddress = null,
        ?string $administrationId = null,
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
            administrationId: $administrationId,
        );
    }

    private function createPaytMessageApiDTO(
        string $id,
        string $senderType,
        string $content,
        ?string $sentAt = null,
        ?string $receivedAt = null,
        ?string $subject = null,
        ?string $creditCaseId = null,
    ): PaytMessageApiDTO {
        return new PaytMessageApiDTO(
            id: $id,
            senderType: $senderType,
            content: $content,
            sentAt: $sentAt,
            receivedAt: $receivedAt,
            subject: $subject,
            creditCaseId: $creditCaseId,
        );
    }

    private function createPaytDebtorWebhookDTO(
        int $id,
        ?string $resourceType = null,
        ?string $companyName = null,
        ?string $name = null,
        ?string $debtorCode = null,
        ?PaytAdministrationWebhookDTO $administration = null,
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

    /**
     * @param PaytInvoice[] $invoices
     */
    private function createPaytCreditCaseWebhookDTO(
        int $id,
        ?string $resourceType = null,
        ?string $creditCaseNumber = null,
        ?string $interest = null,
        ?string $collectionCosts = null,
        ?string $openInterestAndCollectionCosts = null,
        ?string $link = null,
        ?string $publicLink = null,
        array $invoices = [],
        ?PaytDebtorWebhookDTO $debtor = null,
        ?PaytAdministrationWebhookDTO $administration = null,
    ): PaytCreditCaseWebhookDTO {
        return new PaytCreditCaseWebhookDTO(
            id: $id,
            resourceType: $resourceType,
            creditCaseNumber: $creditCaseNumber,
            interest: $interest,
            collectionCosts: $collectionCosts,
            openInterestAndCollectionCosts: $openInterestAndCollectionCosts,
            link: $link,
            publicLink: $publicLink,
            invoices: $invoices,
            debtor: $debtor,
            administration: $administration,
        );
    }
}
