<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient;

use Iterator;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Exceptions\CloudstackNotFoundException;
use Waterfront\Infra\CloudStackClient\DTO\Account;
use Waterfront\Infra\CloudStackClient\DTO\ConsoleEndpoint;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\DTO\Network;
use Waterfront\Infra\CloudStackClient\DTO\Role;
use Waterfront\Infra\CloudStackClient\DTO\ServiceOffering;
use Waterfront\Infra\CloudStackClient\DTO\Template;
use Waterfront\Infra\CloudStackClient\DTO\User;
use Waterfront\Infra\CloudStackClient\DTO\UserKeys;
use Waterfront\Infra\CloudStackClient\DTO\VirtualMachine;
use Waterfront\Infra\CloudStackClient\DTO\Volume;
use Waterfront\Infra\CloudStackClient\DTO\Zone;
use Waterfront\Infra\CloudStackClient\Enums\CloudstackMachineState;
use Waterfront\Infra\CloudStackClient\Enums\TagFilter;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Mapper\AccountMapper;
use Waterfront\Infra\CloudStackClient\Mapper\DomainMapper;
use Waterfront\Infra\CloudStackClient\Mapper\Mapper;
use Waterfront\Infra\CloudStackClient\Mapper\RoleMapper;
use Waterfront\Infra\CloudStackClient\Mapper\ServiceOfferingMapper;
use Waterfront\Infra\CloudStackClient\Mapper\UserKeysMapper;
use Waterfront\Infra\CloudStackClient\Mapper\UserMapper;
use Waterfront\Infra\CloudStackClient\Mapper\VirtualMachineMapper;
use Waterfront\Infra\CloudStackClient\Mapper\VolumeMapper;
use Waterfront\Infra\CloudStackClient\Mapper\ZoneMapper;

class CloudStackClient
{
    public function __construct(
        private readonly CloudStackBaseClient $client,
        private readonly Serializer $serializer,
    ) {
    }

    /**
     * @return Iterator<Domain>
     */
    public function listDomainChildren(string $domainId, ?string $name = null): Iterator
    {
        return $this->list(
            'listDomainChildren',
            [
                'id'      => $domainId,
                'listall' => 'true',
                'name'    => $name ?? '',
            ],
            'domain',
            new DomainMapper()
        );
    }

    /**
     * @throws ClientException
     */
    public function createDomain(string $parentDomainId, string $name): Domain
    {
        $response = $this->client->execute('createDomain', [
            'name'           => $name,
            'parentdomainid' => $parentDomainId,
        ]);

        assert(is_array($response['domain']));

        return (new DomainMapper())($response['domain']);
    }

    /**
     * @throws ClientException
     *
     * @return Network[]
     */
    public function listNetworks(): array
    {
        $parameters = [
            'traffictype'     => 'Guest',
            'listall'         => 'true',
            'type'            => 'shared',
            'canusefordeploy' => 'true',
            'tags'            => [
                [
                    // These tags are used to filter networks that have been created for VPS usage by Operations (CLDIN).
                    'key'   => TagFilter::VPS_NETWORK_KEY->value,
                    'value' => TagFilter::VPS_NETWORK->value,
                ],
            ],
        ];

        $response = $this->client->execute(
            command: 'listNetworks',
            params: $parameters
        );

        if ($response === [] || ! array_key_exists('network', $response)) {
            throw new ClientException('Invalid response when fetching available networks.');
        }

        if ($response['network'] === []) {
            return [];
        }

        /** @var array<Network> $networks */
        $networks = $this->serializer->denormalize($response['network'], Network::class . '[]');

        return $networks;
    }

    /**
     * @throws ClientException
     *
     * @return Template[]
     */
    public function listTemplates(?string $templateSlugTag = null): array
    {
        $parameters = [
                'templatefilter' => 'featured',
                'listall'        => 'true',
            ];

        if ($templateSlugTag !== null) {
            $parameters['tags'] = [
                [
                    'key'   => TagFilter::TEMPLATE_SLUG->value,
                    'value' => $templateSlugTag,
                ],
            ];
        }

        $response = $this->client->execute(
            command: 'listTemplates',
            params: $parameters
        );

        if ($response === [] || ! array_key_exists('template', $response)) {
            throw new ClientException('Invalid response from Cloudstack, missing templates.');
        }

        if ($response['template'] === []) {
            return [];
        }

        /** @var array<Template> $templates */
        $templates = $this->serializer->denormalize($response['template'], Template::class . '[]');

        return $templates;
    }

    /**
     * @return Iterator<Account>
     */
    public function listAccounts(string $domainId, ?string $name = null): Iterator
    {
        return $this->list(
            'listAccounts',
            [
                'domainid' => $domainId,
                'name'     => $name ?? '',
            ],
            'account',
            new AccountMapper()
        );
    }

    public function getConsoleEndpoint(string $virtualMachineId): ConsoleEndpoint
    {
        $response = $this->client->execute(
            command: 'createConsoleEndpoint',
            params: [
                'virtualmachineid' => $virtualMachineId,
            ]
        );

        if ($response === [] || ! array_key_exists('consoleendpoint', $response) || $response['consoleendpoint'] === []) {
            throw new ClientException('Invalid response from Cloudstack, missing `consoleendpoint` object.');
        }

        /** @var ConsoleEndpoint $consoleEndpoint */
        $consoleEndpoint = $this->serializer->denormalize($response['consoleendpoint'], ConsoleEndpoint::class);
        return $consoleEndpoint;
    }

    /**
     * @throws ClientException
     */
    public function createAccount(
        string $domainId,
        string $username,
        string $firstName,
        string $lastName,
        string $email,
        string $password,
        string $roleId,
    ): Account {
        $response = $this->client->execute('createAccount', [
            'domainid'  => $domainId,
            'username'  => $username,
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'email'     => $email,
            'password'  => $password,
            'roleid'    => $roleId,
        ]);

        assert(is_array($response['account']));

        return (new AccountMapper())($response['account']);
    }

    /**
     * @throws ClientException
     */
    public function deleteDomain(string $domainId, bool $cleanup): AsynchronousCloudstackResponse
    {
        $response = $this->client->execute('deleteDomain', [
            'id' => $domainId,
            'cleanup' => $cleanup,
        ]);

        /** @var AsynchronousCloudstackResponse $async */
        $async = $this->serializer->denormalize($response, AsynchronousCloudstackResponse::class);
        return $async;
    }

    /**
     * @return Iterator<Role>
     */
    public function listRoles(?string $name = null): Iterator
    {
        return $this->list('listRoles', [
            'name' => $name ?? '',
        ], 'role', new RoleMapper());
    }

    /**
     * @return Iterator<User>
     */
    public function listUsers(string $domainId, ?string $username = null): Iterator
    {
        return $this->list('listUsers', [
            'domainid' => $domainId,
            'username' => $username ?? '',
        ], 'user', new UserMapper());
    }

    /**
     * @throws ClientException
     */
    public function getUserKeys(string $userId): ?UserKeys
    {
        $response = $this->client->execute('getUserKeys', [
            'id' => $userId,
        ]);

        assert(is_array($response['userkeys']));

        return (new UserKeysMapper())($response['userkeys']);
    }

    /**
     * @throws ClientException
     */
    public function registerUserKeys(string $userId): ?UserKeys
    {
        $response = $this->client->execute('registerUserKeys', [
            'id' => $userId,
        ]);

        assert(is_array($response['userkeys']));

        return (new UserKeysMapper())($response['userkeys']);
    }

    /**
     *
     * @return Iterator<Volume>
     */
    public function listVolumes(?string $domainId = null, ?string $name = null): Iterator
    {
        $params = [];

        if ($domainId !== null) {
            $params['domainid'] = $domainId;
        }

        if ($name !== null) {
            $params['name'] = $name;
        }

        return $this->list('listVolumes', $params, 'volume', new VolumeMapper());
    }

    /**
     * @throws CloudstackNotFoundException
     */
    public function getVirtualMachine(string $id): VirtualMachine
    {
        try {
            $response = $this->client->execute('listVirtualMachines', [
                'id' => $id,
            ]);
        } catch (ClientException $exception) {
            throw CloudstackNotFoundException::vmNotFound($id, $exception);
        }

        assert(is_array($response['virtualmachine']));

        $vm = $response['virtualmachine'][0] ?? null;

        if ($vm === null) {
            throw CloudstackNotFoundException::vmNotFound($id);
        }

        return $this->serializer->denormalize($vm, VirtualMachine::class);
    }

    /**
     * New method to list all virtual machines without using an iterator
     * and makes use of the serializer to denormalize the response.
     *
     * @throws ClientException
     *
     * @return VirtualMachine[]
     */
    public function listAllVirtualMachines(): array
    {
        $listAll = $this->client->execute('listVirtualMachines', [
            'listall' => 'true',
        ]);

        if ($listAll === [] || ! array_key_exists('virtualmachine', $listAll)) {
            return [];
        }

        $virtualMachines = [];

        if (! is_array($listAll['virtualmachine']) || count($listAll['virtualmachine']) === 0) {
            return $virtualMachines;
        }

        foreach ($listAll['virtualmachine'] as $vm) {
            /** @var VirtualMachine $virtualMachine */
            $virtualMachine = $this->serializer->denormalize($vm, VirtualMachine::class);
            $virtualMachines[] = $virtualMachine;
        }

        return $virtualMachines;
    }

    /**
     * @return Iterator<VirtualMachine>
     */
    public function listVirtualMachines(
        ?string $id = null,
        ?string $domainId = null,
        ?string $name = null,
        ?CloudstackMachineState $state = null
    ): Iterator {
        $params = [
            'listall' => 'true',
        ];

        if ($domainId !== null) {
            $params['domainid'] = $domainId;
        }

        if ($name !== null) {
            $params['name'] = $name;
        }

        if ($state !== null) {
            $params['state'] = $state->value;
        }

        return $this->list('listVirtualMachines', $params, 'virtualmachine', new VirtualMachineMapper());
    }

    /**
     * @throws ClientException
     */
    public function updateUser(string $id, string $password): User
    {
        $response = $this->client->execute('updateUser', [
            'id'       => $id,
            'password' => $password,
        ]);

        assert(is_array($response['user']));

        return (new UserMapper())($response['user']);
    }

    /**
     * @throws ClientException
     */
    public function listZones(): Zone
    {
        $response = $this->client->execute('listZones');

        assert(is_array($response['zone']));

        return (new ZoneMapper())($response['zone'][0]);
    }

    /**
     * @return Iterator<ServiceOffering>
     */
    public function listServiceOfferings(string $domainId): Iterator
    {
        return $this->list(
            'listServiceOfferings',
            [
                'domainid' => $domainId,
            ],
            'serviceoffering',
            new ServiceOfferingMapper()
        );
    }

    /**
     * @throws ClientException
     */
    public function startVirtualMachine(string $id): void
    {
        $this->client->execute('startVirtualMachine', [
            'id' => $id,
        ]);
    }

    /**
     * @throws ClientException
     */
    public function rebootVirtualMachine(string $id): void
    {
        $this->client->execute('rebootVirtualMachine', [
            'id' => $id,
        ]);
    }

    /**
     * @throws ClientException
     */
    public function stopVirtualMachine(string $id): void
    {
        $this->client->execute('stopVirtualMachine', [
            'id' => $id,
        ]);
    }

    /**
     * @throws ClientException
     */
    public function restoreVirtualMachine(
        string $virtualMachineId,
        ?string $templateId = null
    ): AsynchronousCloudstackResponse {
        $params = ['virtualmachineid' => $virtualMachineId];
        if ($templateId !== null) {
            $params['templateid'] = $templateId;
        }

        /** @var mixed[] $response */
        $response = $this->client->execute('restoreVirtualMachine', $params);

        /** @var AsynchronousCloudstackResponse $async */
        $async = $this->serializer->denormalize($response, AsynchronousCloudstackResponse::class);
        return $async;
    }

    /**
     * @throws ClientException
     */
    public function destroyVirtualMachine(string $id, bool $expunge = false): AsynchronousCloudstackResponse
    {
        /** @var string[] $jobResponse */
        $jobResponse = $this->client->execute('destroyVirtualMachine', [
            'id' => $id,
            'expunge' => $expunge,
        ]);

        /** @var AsynchronousCloudstackResponse $asyncJobresponse */
        $asyncJobresponse = $this->serializer->denormalize($jobResponse, AsynchronousCloudstackResponse::class);

        return $asyncJobresponse;
    }

    /**
     * @return mixed[]
     */
    public function registerSshKeyPair(string $name, string $publicKey): array
    {
        try {
            return $this->client->execute('registerSSHKeyPair', [
                'name' => $name,
                'publickey' => $publicKey,
            ]);
        } catch (ClientException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @return mixed[]
     */
    public function deleteSshKeyPair(string $name): array
    {
        try {
            return $this->client->execute('deleteSSHKeyPair', [
                'name' => $name,
            ]);
        } catch (ClientException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function resetSshKeyForVirtualMachine(string $vmId, string $keyPair): AsynchronousCloudstackResponse
    {
        try {
            $jobResponse = $this->client->execute('resetSSHKeyForVirtualMachine', [
                'id' => $vmId,
                'keypair' => $keyPair,
            ]);

            /** @var AsynchronousCloudstackResponse $asyncJobResponse */
            $asyncJobResponse = $this->serializer->denormalize($jobResponse, AsynchronousCloudstackResponse::class);

            return $asyncJobResponse;
        } catch (ClientException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Deploys a new VM on cloudstack. Cloudstack returns a new
     * job id which will be run on there system asynchronous
     * that we can check by the returned job.
     *
     * @throws CloudstackException|ClientException
     */
    public function deployVirtualMachine(
        string $serviceOfferingId,
        string $osTemplateId,
        string $zoneId,
        string $domainId,
        string $account,
        string $securityGroupId,
        string $displayName,
        string $hostname,
        string $fqdn,
        ?string $keyPair,
        ?string $networkId = null
    ): AsynchronousCloudstackResponse {
        $userData = '#cloud-config
                        manage_etc_hosts: true
                        fqdn: ' . $fqdn . '
                        hostname: ' . $hostname . '
                        timezone: Europe/Amsterdam
                        ssh_pwauth: True
                        chpasswd:
                            expire: false';

        $parameters = [
            'serviceofferingid' => $serviceOfferingId,
            'templateid'        => $osTemplateId,
            'zoneId'            => $zoneId,
            'account'           => $account,
            'domainid'          => $domainId,
            'securitygroupids'  => $securityGroupId,
            'displayname'       => $displayName,
            'userdata'          => base64_encode($userData),
            'networkids'        => $networkId,
        ];

        /**
         * The request parameter is optional in the cloudstack.
         * And can be null on our side when a customer choose to login with credentials instead of a public key.
         */
        if ($keyPair !== null) {
            $parameters['keypair'] = $keyPair;
        }

        /** @var array<string, string> $job */
        $job = $this->client->execute('deployVirtualMachine', $parameters);

        /** @var AsynchronousCloudstackResponse $asyncJobresponse */
        $asyncJobresponse = $this->serializer->denormalize($job, AsynchronousCloudstackResponse::class);
        return $asyncJobresponse;
    }

    /**
     * @throws ClientException
     */
    public function createSecurityGroup(string $account, string $domainId, ?string $name = null): string
    {
        $response = $this->client->execute('createSecurityGroup', [
            'name'        => $name ?? $account,
            'description' => 'Default for ' . $account,
            'account'     => $account,
            'domainid'    => $domainId,
        ]);

        assert(is_array($response['securitygroup']));
        assert(is_string($response['securitygroup']['id']));

        return $response['securitygroup']['id'];
    }

    /**
     * @throws ClientException
     */
    public function authorizeSecurityGroupIngress(string $account, string $domainId, string $securityGroupId): void
    {
        $this->client->execute('authorizeSecurityGroupIngress', [
            'account'         => $account,
            'domainid'        => $domainId,
            'cidrlist'        => '0.0.0.0/0',
            'protocol'        => 'ALL',
            'securitygroupid' => $securityGroupId,
        ]);

        $this->client->execute('authorizeSecurityGroupIngress', [
            'account'         => $account,
            'domainid'        => $domainId,
            'cidrlist'        => '::/0',
            'protocol'        => 'ALL',
            'securitygroupid' => $securityGroupId,
        ]);
    }

    /**
     * @throws ClientException
     */
    public function resetPasswordForVirtualMachine(string $vmId, string $password): AsynchronousCloudstackResponse
    {
        /** @var array<string, string> $jobResponse */
        $jobResponse = $this->client->execute('resetPasswordForVirtualMachine', [
            'id' => $vmId,
            'password' => $password,
        ]);

        /** @var AsynchronousCloudstackResponse $asyncJobresponse */
        $asyncJobresponse = $this->serializer->denormalize($jobResponse, AsynchronousCloudstackResponse::class);

        return $asyncJobresponse;
    }

    public function getBaseClient(): CloudStackBaseClient
    {
        return $this->client;
    }

    /**
     * @param array<string, string|bool> $parameters
     *
     * @template T
     *
     * @param Mapper<T> $mapper
     *
     * @return Iterator<T>
     */
    private function list(string $command, array $parameters, string $type, Mapper $mapper): Iterator
    {
        return new CloudStackPaginationIterator($this->client, $command, $parameters, $type, $mapper);
    }
}
