<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\DTO\DeleteSshKeyPairResponse;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\CloudstackException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;

class CloudstackService
{
    public function __construct(
        private readonly AdminClientFactoryInterface $adminClientFactory,
        private readonly ClientFactoryInterface $clientFactory,
        private readonly Serializer $serializer,
    ) {
    }

    /**
     *
     * @throws CloudstackException
     *
     * @return array<int, array<string, string>>
     */
    public function getServiceOfferings(int $environmentId): array
    {
        try {
            $environment = Environment::find($environmentId);
            if (! $environment instanceof Environment) {
                throw new CloudstackException('Environment not found');
            }
            $adminClient = $this->adminClientFactory->create($environment);
            $serviceOfferings = $adminClient->listServiceOfferings($environment->domain_id);
            $serviceOfferingsArray = iterator_to_array($serviceOfferings);
            $selectServiceOfferingsArray = [];
            foreach ($serviceOfferingsArray as $serviceOffering) {
                $selectServiceOfferingsArray[] = ['value' => $serviceOffering->id, 'label' => $serviceOffering->name];
            }

            return $selectServiceOfferingsArray;
        } catch (AdminClientFactoryException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws ClientException
     */
    public function retrieveJobStatus(string $jobId, CloudStackBaseClient $client): AsynchronousCloudstackResponse
    {
        /** @var array<string, array<int|string, mixed>|int|string> $jobResult */
        $jobResult = $client->execute('queryAsyncJobResult', ['jobid' => $jobId]);

        /** @var AsynchronousCloudstackResponse $asyncJobresponse */
        $asyncJobresponse = $this->serializer->denormalize($jobResult, AsynchronousCloudstackResponse::class);
        return $asyncJobresponse;
    }

    public function getBaseClientFromSubscription(VirtualMachineDeployment $deployment): CloudStackBaseClient
    {
        return $this->clientFactory->create($deployment->managerDomainDeployment)->getBaseClient();
    }

    /**
     * @throws CloudstackException
     *
     * @return mixed[]
     */
    public function registerSshKeyPair(ManagerDomainDeployment $managerDomainDeployment, string $name, string $publicKey): array
    {
        try {
            $client = $this->clientFactory->create($managerDomainDeployment);
            return $client->registerSshKeyPair($name, $publicKey);
        } catch (ClientFactoryException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * @throws CloudstackException
     */
    public function deleteSshKeyPair(ManagerDomainDeployment $managerDomainDeployment, string $name): DeleteSshKeyPairResponse
    {
        try {
            $client = $this->clientFactory->create($managerDomainDeployment);
            $clientResponse = $client->deleteSshKeyPair($name);

            /** @var DeleteSshKeyPairResponse $deleteResponse */
            $deleteResponse = $this->serializer->denormalize($clientResponse, DeleteSshKeyPairResponse::class);
            return $deleteResponse;
        } catch (ClientFactoryException $exception) {
            throw new CloudstackException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
