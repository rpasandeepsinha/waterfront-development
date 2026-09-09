<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Ferry\Mappers\CsvParser;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaImportDirectAdminHostingServersAction extends NovaImportHostingServersAction
{
    public function __construct(
        CsvParser $csvParser,
        TranslatorInterface $translator,
        DomainNameRule $domainNameRule,
    ) {
        parent::__construct($csvParser, $translator);

        $hostingServersTable = new Server()->getTable();

        $this->validationRules = [
            '*' => ['array'],
            '*.owner' => [
                'required',
                'string',
            ],
            '*.hostname' => [
                'required',
                'distinct',
                'string',
                "unique:$hostingServersTable,hostname,NULL,id,deleted_at,NULL",
                $domainNameRule,
            ],
            '*.ipv4' => [
                'required',
                'distinct',
                'ipv4',
                "unique:$hostingServersTable,ipv4,NULL,id,deleted_at,NULL",
            ],
            '*.ipv6' => [
                'nullable',
                'distinct',
                'ipv6',
                "unique:$hostingServersTable,ipv6,NULL,id,deleted_at,NULL",
            ],
            '*.port' => [
                'required',
                'numeric',
            ],
            '*.username' => [
                'required',
                'string',
            ],
            '*.password' => [
                'required',
                'string',
            ],
            '*.login_key' => [
                'nullable',
                'string',
            ],
            '*.allow_new_websites' => [
                'required',
                'boolean',
            ],
            '*.maximum_websites' => [
                'required',
                'numeric',
            ],
            '*.use_ssl' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.import_directadmin_hosting_servers');
    }

    /**
     * @param array<string, string|null> $serverData
     */
    protected function createServer(array $serverData): void
    {
        $server = new Server();
        $server->owner = $serverData['owner'];
        $server->type = ServerType::DIRECTADMIN;
        $server->name = $serverData['hostname']; // name same as hostname
        $server->hostname = (string) $serverData['hostname'];
        $server->port = intval($serverData['port']);
        $server->ipv4 = $serverData['ipv4'];
        $server->ipv6 = $serverData['ipv6'];
        $server->allow_new_websites = (bool) $serverData['allow_new_websites'];
        $server->maximum_websites = intval($serverData['maximum_websites']);
        $server->use_ssl = (bool) $serverData['use_ssl'];
        $server->username = $serverData['username'];
        $server->password = $serverData['password'];
        $server->loginkey = $serverData['login_key'];

        $server->save();
    }
}
