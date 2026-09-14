<?php

declare(strict_types=1);

namespace Tests\Domain\Servers\Actions;

use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Servers\Actions\ImportHostingServersAction;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(ImportHostingServersAction::class)]
class ImportHostingServersActionTest extends IntegrationTestCase
{
    private ImportHostingServersAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        /* Backend JSON translations are pulled from S3 at runtime and are not
         * loaded under test, so register the ones this action reports with. */
        Lang::addLines([
            'hosting.server-import.error.line' => 'Regel :line: :message',
            'hosting.server-import.error.line-field' => 'Regel :line, :field: :message',
            'hosting.server-import.error.too-many-rows' => 'Het bestand bevat :found regels; er kunnen er maximaal :max tegelijk geïmporteerd worden.',
            'hosting.server-import.error.column-count' => 'Regel :line: deze regel heeft :found kolommen, de header rij heeft er :expected.',
        ], 'nl');

        $this->action = self::resolve(ImportHostingServersAction::class);
    }

    #[Test]
    public function canImportPleskServers(): void
    {
        self::assertDatabaseCount('hosting_servers', 0);

        $importedCount = $this->action->execute(
            ServerType::PLESK,
            $this->csv('server_import_plesk_success'),
        );

        self::assertSame(1, $importedCount);

        $pleskServer = Server::query()
            ->where('type', ServerType::PLESK)
            ->where('hostname', 'import-nameserver-plesk.dev')
            ->firstOrFail();

        self::assertSame('sandwave', $pleskServer->owner);
        self::assertSame('import-nameserver-plesk.dev', $pleskServer->name);
        self::assertSame('127.0.0.1', $pleskServer->ipv4);
        self::assertSame('::1', $pleskServer->ipv6);
        self::assertFalse($pleskServer->allow_new_websites);
        self::assertSame(400, $pleskServer->maximum_websites);
        self::assertSame('test_secret_key', $pleskServer->secret_key);
        self::assertSame('plesk-php82-fastcgi', $pleskServer->php_version);
        self::assertSame(2222, $pleskServer->port);
        self::assertFalse($pleskServer->use_ssl);

        // DirectAdmin-only columns must stay untouched for a Plesk import.
        self::assertNull($pleskServer->username);
        self::assertEmpty($pleskServer->password);
        self::assertEmpty($pleskServer->loginkey);
    }

    #[Test]
    public function canImportDirectAdminServers(): void
    {
        self::assertDatabaseCount('hosting_servers', 0);

        $importedCount = $this->action->execute(
            ServerType::DIRECTADMIN,
            $this->csv('server_import_directadmin_success'),
        );

        self::assertSame(1, $importedCount);

        $directAdminServer = Server::query()
            ->where('type', ServerType::DIRECTADMIN)
            ->where('hostname', 'import-nameserver-directadmin.dev')
            ->firstOrFail();

        self::assertSame('sandwave', $directAdminServer->owner);
        self::assertSame('import-nameserver-directadmin.dev', $directAdminServer->name);
        self::assertSame('127.0.0.1', $directAdminServer->ipv4);
        self::assertSame('::1', $directAdminServer->ipv6);
        self::assertTrue($directAdminServer->allow_new_websites);
        self::assertSame(300, $directAdminServer->maximum_websites);
        self::assertSame('test_username', $directAdminServer->username);
        self::assertSame('test_password', $directAdminServer->password);
        self::assertSame('test_login_key', $directAdminServer->loginkey);
        self::assertSame(1111, $directAdminServer->port);
        self::assertTrue($directAdminServer->use_ssl);

        // Plesk-only columns must stay untouched for a DirectAdmin import.
        self::assertEmpty($directAdminServer->secret_key);
    }

    #[Test]
    public function pleskCsvIsRejectedWhenImportedAsDirectAdmin(): void
    {
        try {
            $this->action->execute(
                ServerType::DIRECTADMIN,
                $this->csv('server_import_plesk_success'),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['csv_upload'];

            self::assertContains('Regel 2, username: Dit veld is verplicht.', $messages);
            self::assertContains('Regel 2, password: Dit veld is verplicht.', $messages);
        }

        self::assertDatabaseCount('hosting_servers', 0);
    }

    #[Test]
    public function numberOfColumnsReportsTheOffendingLine(): void
    {
        try {
            $this->action->execute(
                ServerType::PLESK,
                $this->csv('server_import_columns_dont_match'),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                ['Regel 2: deze regel heeft 10 kolommen, de header rij heeft er 13.'],
                $exception->errors()['csv_upload'],
            );
        }

        self::assertDatabaseCount('hosting_servers', 0);
    }

    #[Test]
    public function invalidRowsAbortTheWholeImport(): void
    {
        try {
            $this->action->execute(
                ServerType::PLESK,
                $this->csv('server_import_validation_errors'),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'Regel 2, hostname: Dit veld bevat geen geldige domeinnaam.',
                    'Regel 2, ipv4: Dit veld is geen valide versie 4 ip adres.',
                    'Regel 2, ipv6: Dit veld is geen valide versie 6 ip adres.',
                    'Regel 2, secret_key: Dit veld is verplicht.',
                ],
                $exception->errors()['csv_upload'],
            );
        }

        self::assertDatabaseCount('hosting_servers', 0);
    }

    #[Test]
    public function reportsTheLineNumberOfTheOffendingRow(): void
    {
        try {
            $this->action->execute(
                ServerType::PLESK,
                $this->csv('server_import_plesk_bad_third_row'),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            // The bad row is the third data row, so line 4 of the file.
            self::assertSame(
                [
                    'Regel 4, hostname: Dit veld bevat geen geldige domeinnaam.',
                    'Regel 4, ipv4: Dit veld is geen valide versie 4 ip adres.',
                ],
                $exception->errors()['csv_upload'],
            );
        }

        self::assertDatabaseCount('hosting_servers', 0);
    }

    #[Test]
    public function emptyPhpVersionFallsBackToTheColumnDefault(): void
    {
        $importedCount = $this->action->execute(
            ServerType::PLESK,
            $this->csv('server_import_plesk_no_php_version'),
        );

        self::assertSame(1, $importedCount);

        $pleskServer = Server::query()->where('hostname', 'import-no-php-version.dev')->firstOrFail();

        self::assertSame('plesk-php71-fastcgi', $pleskServer->php_version);
    }

    #[Test]
    public function rowsCollidingWithAnExistingServerAreRejected(): void
    {
        ServerFactory::new()->createOne([
            'hostname' => 'import-nameserver-plesk.dev',
            'ipv4' => '127.0.0.1',
            'ipv6' => '::99',
        ]);

        try {
            $this->action->execute(
                ServerType::PLESK,
                $this->csv('server_import_plesk_success'),
            );
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'Regel 2, hostname: Dit veld is niet uniek.',
                    'Regel 2, ipv4: Dit veld is niet uniek.',
                ],
                $exception->errors()['csv_upload'],
            );
        }

        self::assertDatabaseCount('hosting_servers', 1);
    }

    #[Test]
    public function softDeletedServersDoNotBlockAnImport(): void
    {
        $server = ServerFactory::new()->createOne([
            'hostname' => 'import-nameserver-plesk.dev',
            'ipv4' => '127.0.0.1',
            'ipv6' => '::1',
        ]);
        $server->delete();

        $importedCount = $this->action->execute(
            ServerType::PLESK,
            $this->csv('server_import_plesk_success'),
        );

        self::assertSame(1, $importedCount);
    }

    #[Test]
    public function filesOverTheRowLimitAreRejected(): void
    {
        $header = 'owner,hostname,port,ipv4,ipv6,secret_key,allow_new_websites,maximum_websites,php_version,use_ssl';
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $ipv4 = sprintf('10.40.%d.%d', intdiv($i, 256), $i % 256);
            $rows[] =
                "sandwave,cap-$i.example.com,8443,$ipv4,2001:db8:4::"
                . dechex($i)
                . ",key-$i,1,400,plesk-php82-fastcgi,1";
        }

        try {
            $this->action->execute(ServerType::PLESK, $header . "\n" . implode("\n", $rows) . "\n");
            self::fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                ['Het bestand bevat 1001 regels; er kunnen er maximaal 1000 tegelijk geïmporteerd worden.'],
                $exception->errors()['csv_upload'],
            );
        }

        // Rejected before any row-level validation or writing.
        self::assertDatabaseCount('hosting_servers', 0);
    }

    private function csv(string $name): string
    {
        return (string) file_get_contents(__DIR__ . "/data/$name.csv");
    }
}
