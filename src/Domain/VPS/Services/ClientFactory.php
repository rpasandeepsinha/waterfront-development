<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use GuzzleHttp\ClientInterface;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\User;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;

class ClientFactory implements ClientFactoryInterface
{
    /** @var CloudStackClient[] */
    private array $clients = [];

    public function __construct(
        private readonly AdminClientFactory $adminClientFactory,
        private readonly ClientInterface $guzzleClient
    ) {
    }

    /**
     * @throws ClientFactoryException
     */
    public function create(ManagerDomainDeployment $deployment): CloudStackClient
    {
        if ($deployment->domain_id === null) {
            throw new ClientFactoryException(sprintf(
                'ubscription domain with id: %s not set',
                $deployment->id
            ));
        }

        $hash = $deployment->environment->api_url . '|' . $deployment->domain_id;

        if (array_key_exists($hash, $this->clients)) {
            return $this->clients[$hash];
        }

        try {
            $adminClient = $this->adminClientFactory->create($deployment->environment);
        } catch (AdminClientFactoryException $e) {
            throw new ClientFactoryException((string) $e, $e->getCode(), $e);
        }

        try {
            $users = $adminClient->listUsers($deployment->domain_id, $deployment->username);
        } catch (ClientException $e) {
            throw new ClientFactoryException('List users failed', $e->getCode(), $e);
        }

        if (! $users->valid()) {
            throw new ClientFactoryException(sprintf(
                'loudStack user for given name: %s not found',
                $deployment->username
            ));
        }

        /** @var User $user */
        $user = $users->current();

        try {
            $userKeys = $adminClient->getUserKeys($user->id) ?? $adminClient->registerUserKeys($user->id);
        } catch (ClientException $e) {
            throw new ClientFactoryException('Failed to get user keys', $e->getCode(), $e);
        }

        if ($userKeys === null) {
            throw new ClientFactoryException('User keys empty');
        }

        return $this->clients[$hash] = new CloudStackClient(
            new CloudStackBaseClient(
                $deployment->environment->api_url,
                $userKeys->apiKey,
                $userKeys->secretKey,
                $this->guzzleClient
            ),
            CloudstackSerializerFactory::get()
        );
    }
}
