<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use GuzzleHttp\Psr7\Response;
use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\LegacyRedirectingServerFactory;
use Tests\Infra\PowerDnsClient\PowerDnsMockHelper;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\RedirectMigrationPipe;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectDatabaseRepository;
use Waterfront\Domain\Redirects\Services\RedirectsDatabase\RedirectsRepositoryInterface;
use Waterfront\Infra\PowerDnsClient\Enums\PowerDnsZoneKind;

#[CoversClass(RedirectMigrationPipe::class)]
#[AllowMockObjectsWithoutExpectations]
class RedirectMigrationPipeTest extends IntegrationTestCase
{
    use PowerDnsMockHelper;

    /**
     * @param array<mixed> $expectedValidationResults
     *
     * @throws JsonException
     * @throws Exception
     */
    #[DataProvider('redirectValidationPipelineProvider')]
    #[Test]
    public function redirectValidationPipe(
        bool $sourceValidationMismatch,
        bool $pdnsZoneNotFound,
        bool $pdnsException,
        bool $redirectExistsInDatabase,
        bool $noRedirectServerConfiguredInDatabase,
        bool $hasNoData,
        array $expectedValidationResults,
    ): void {
        LegacyRedirectingServerFactory::new()->createOne([
            'original_business_unit' => 'testmigration',
        ]);

        $customer = include __DIR__ . '/data/customer_correct.php';
        if ($noRedirectServerConfiguredInDatabase) {
            $customer['referenceName'] = 'unconfigured_bu';
        }

        $subscriptions = include __DIR__ . '/data/subscriptions_correct.php';

        if ($sourceValidationMismatch) {
            $subscriptions['redirects'][0]['domain'] = 'domain_different_from_source.com';
        }

        if ($hasNoData) {
            unset($subscriptions['redirects'][0]['redirect_data']);
        }

        $responses = [];

        if ($pdnsZoneNotFound) {
            $responses[] = new Response(404);
        } elseif ($pdnsException) {
            // Usually our hydrator is at fault, but this is fine too
            $responses[] = new Response(
                500,
                [],
                'random PowerDNS error',
            );
        } else {
            $responses[] = new Response(
                200,
                [],
                $this->getMockedZoneResponseBody(
                    'zone.nl',
                    [],
                    PowerDnsZoneKind::MASTER->value,
                ),
            );
        }

        $this->pdns($this->makePdnsWithMultipleResponses($responses));

        $redirectRepositoryMock = self::createStub(RedirectDatabaseRepository::class);

        if ($redirectExistsInDatabase) {
            $fakeRedirect = new RedirectDatabaseRepository();

            $redirectRepositoryMock
                ->method('findBySourceForMigrations')
                ->willReturnCallback(
                    fn (string $value): ?RedirectDatabaseRepository => $value === 'test-dns-intern-10.nl'
                        ? $fakeRedirect
                        : null,
                );
        } else {
            $redirectRepositoryMock->method('findBySourceForMigrations')->willReturn(null);
        }

        $this->app->bind(
            RedirectsRepositoryInterface::class,
            fn (): RedirectsRepositoryInterface => $redirectRepositoryMock,
        );

        $reference = 'unique_reference_for_adf';

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions,
        );

        $sslMigrationPipe = self::resolve(RedirectMigrationPipe::class);

        $validationPayload = $sslMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload,
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame($expectedValidationResults, $validationPayload->validationResults);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function redirectValidationPipelineProvider(): iterable
    {
        yield 'Validation source not related to domain' => [
            'sourceValidationMismatch' => true, // use a domain that is not part of the source
            'pdnsZoneNotFound' => false, // fail on finding the dns zone in pdns.
            'pdnsException' => false, // getting zone gives exception (usually hydrator failure)
            'redirectExistsInDatabase' => false, // redirect exists in the redirecting database
            'noRedirectServerConfiguredInDatabase' => false, // no server setup for redirects,
            'hasNoData' => false,
            'expectedValidationResults' => [
                'redirect_migration' => [
                    [
                        'id' => 'laravel_validation',
                        'message' => [
                            '0.source' => [
                                'The given subscription domain domain_different_from_source.com is not compatible with the given source test-dns-intern-10.nl',
                            ],
                            '1.source' => [
                                'The given subscription domain domain_different_from_source.com is not compatible with the given source subdomain.test-dns-intern-10.nl',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                        'message' => 'redirect_migration reference: unique_reference_for_adf',
                    ],
                ],
            ], // expected payload for verification
        ];

        yield 'Zone does not exist' => [
            'sourceValidationMismatch' => false,
            'pdnsZoneNotFound' => true,
            'pdnsException' => false,
            'redirectExistsInDatabase' => false,
            'noRedirectServerConfiguredInDatabase' => false,
            'hasNoData' => false,
            'expectedValidationResults' => [
                'redirect_migration' => [
                    [
                        'id' => MigrationValidation::REDIRECT_DNS_ZONE_NOT_FOUND->value,
                        'message' => 'Zone test-dns-intern-10.nl does not exist. The migration will only run the redirect migration itself.',
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                        'message' => 'redirect_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'PowerDNS unexpected exception' => [
            'sourceValidationMismatch' => false,
            'pdnsZoneNotFound' => false,
            'pdnsException' => true,
            'redirectExistsInDatabase' => false,
            'noRedirectServerConfiguredInDatabase' => false,
            'hasNoData' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::REDIRECT_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::REDIRECT_DNS_ZONE_UNEXPECTED_EXCEPTION->value,
                        'message' => 'Fetching PowerDNS zone test-dns-intern-10.nl gave unexpected exception: random PowerDNS error',
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                        'message' => 'redirect_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'PowerDNS unexpected exception + missing redirect legacy server configuration in db' => [
            'sourceValidationMismatch' => false,
            'pdnsZoneNotFound' => false,
            'pdnsException' => true,
            'redirectExistsInDatabase' => false,
            'noRedirectServerConfiguredInDatabase' => true,
            'hasNoData' => false,
            'expectedValidationResults' => [
                MigrationValidationPipes::REDIRECT_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::REDIRECT_DNS_ZONE_UNEXPECTED_EXCEPTION->value,
                        'message' => 'Fetching PowerDNS zone test-dns-intern-10.nl gave unexpected exception: random PowerDNS error',
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_NO_LEGACY_SERVERS_CONFIGURED->value,
                        'message' => 'There are no Legacy Redirect Servers configured for this business unit unconfigured_bu',
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                        'message' => 'redirect_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Validation payload is empty on the subscription should be allowed' => [
            'sourceValidationMismatch' => false,
            'pdnsZoneNotFound' => false,
            'pdnsException' => false,
            'redirectExistsInDatabase' => false,
            'noRedirectServerConfiguredInDatabase' => true,
            'hasNoData' => true,
            'expectedValidationResults' => [
                MigrationValidationPipes::REDIRECT_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::REDIRECT_NO_LEGACY_SERVERS_CONFIGURED->value,
                        'message' => 'There are no Legacy Redirect Servers configured for this business unit unconfigured_bu',
                    ],
                    [
                        'id' => MigrationValidation::REDIRECT_PIPE_PASSED->value,
                        'message' => 'redirect_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];
    }
}
