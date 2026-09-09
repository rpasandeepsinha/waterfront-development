<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Hosting\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\ActionRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Hosting\Actions\NovaImportDirectAdminHostingServersAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaImportPleskHostingServersAction;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

#[CoversClass(NovaImportPleskHostingServersAction::class)]
class ImportHostingServersTest extends IntegrationTestCase
{
    #[Test]
    public function canImportPleskCSVSuccessfully(): void
    {
        [$actionRequest, $actionFields] = $this->getTestActionRequestAndActionFields(__DIR__ . '/data/server_import_plesk_success.csv');

        self::assertDatabaseCount('hosting_servers', 0);

        $action = self::resolve(NovaImportPleskHostingServersAction::class);
        $action->validateFields($actionRequest);
        $action->handle($actionFields);

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
        self::assertNull($pleskServer->username);
        self::assertEmpty($pleskServer->password);
        self::assertEmpty($pleskServer->loginkey);
        self::assertSame('test_secret_key', $pleskServer->secret_key);
        self::assertSame(2222, $pleskServer->port);
        self::assertFalse($pleskServer->use_ssl);
    }

    #[Test]
    public function canImportDirectAdminCSVSuccessfully(): void
    {
        [$actionRequest, $actionFields] = $this->getTestActionRequestAndActionFields(__DIR__ . '/data/server_import_directadmin_success.csv');

        self::assertDatabaseCount('hosting_servers', 0);

        $action = self::resolve(NovaImportDirectAdminHostingServersAction::class);
        $action->validateFields($actionRequest);
        $action->handle($actionFields);

        $directadminServer = Server::query()
            ->where('type', ServerType::DIRECTADMIN)
            ->where('hostname', 'import-nameserver-directadmin.dev')
            ->firstOrFail();

        self::assertSame('sandwave', $directadminServer->owner);
        self::assertSame('import-nameserver-directadmin.dev', $directadminServer->name);
        self::assertSame('127.0.0.1', $directadminServer->ipv4);
        self::assertSame('::1', $directadminServer->ipv6);
        self::assertTrue($directadminServer->allow_new_websites);
        self::assertSame(300, $directadminServer->maximum_websites);
        self::assertSame('test_username', $directadminServer->username);
        self::assertSame('test_password', $directadminServer->password);
        self::assertSame('test_login_key', $directadminServer->loginkey);
        self::assertEmpty($directadminServer->secret_key);
        self::assertSame(1111, $directadminServer->port);
        self::assertTrue($directadminServer->use_ssl);
    }

    #[Test]
    public function numberOfColumnsDoesNotMatch(): void
    {
        [$actionRequest, $actionFields] = $this->getTestActionRequestAndActionFields(__DIR__ . '/data/server_import_columns_dont_match.csv');

        self::assertDatabaseCount('hosting_servers', 0);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIs('nova-action.error.number_of_columns_does_not_match');

        $action = self::resolve(NovaImportPleskHostingServersAction::class);

        try {
            $action->validateFields($actionRequest);
            $action->handle($actionFields);
        } finally {
            self::assertDatabaseCount('hosting_servers', 0);
        }
    }

    #[Test]
    public function validationErrors(): void
    {
        [$actionRequest, $actionFields] = $this->getTestActionRequestAndActionFields(__DIR__ . '/data/server_import_validation_errors.csv');

        self::assertDatabaseCount('hosting_servers', 0);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageIs(
            '0.php_version: Dit veld is verplicht.<br/>' .
            '0.secret_key: Dit veld is verplicht.<br/>' .
            '0.ipv4: Dit veld is geen valide versie 4 ip adres.<br/>' .
            '0.ipv6: Dit veld is geen valide versie 6 ip adres.<br/>' .
            '0.hostname: Dit veld bevat geen geldige domeinnaam.<br/>'
        );

        $action = self::resolve(NovaImportPleskHostingServersAction::class);

        try {
            $action->validateFields($actionRequest);
            $action->handle($actionFields);
        } finally {
            self::assertDatabaseCount('hosting_servers', 0);
        }
    }

    /**
     * @return array{0: ActionRequest, 1: ActionFields}>
     */
    private function getTestActionRequestAndActionFields(string $fileLocation): array
    {
        $csv = (string) file_get_contents($fileLocation);

        $payload = [
            'csv_upload' => UploadedFile::fake()
                ->createWithContent(
                    'server_import.csv',
                    $csv,
                ),
        ];

        return [
            new ActionRequest($payload, [], [], [], $payload),
            new ActionFields(new Collection($payload), new Collection([])),
        ];
    }
}
