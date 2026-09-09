<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use Exception;
use Illuminate\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Serializer;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\VPS\DTO\AsynchronousCloudstackResponse;
use Waterfront\Domain\VPS\Exceptions\AdminClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\ClientFactoryException;
use Waterfront\Domain\VPS\Exceptions\ManagerDomainException;
use Waterfront\Domain\VPS\Interfaces\AdminClientFactoryInterface;
use Waterfront\Domain\VPS\Interfaces\ClientFactoryInterface;
use Waterfront\Domain\VPS\Jobs\DeleteDomainJob;
use Waterfront\Domain\VPS\Models\CloudstackJob;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Infra\CloudStackClient\DTO\Domain;
use Waterfront\Infra\CloudStackClient\DTO\User;
use Waterfront\Infra\CloudStackClient\Exceptions\ClientException;
use Waterfront\Infra\PasswordGenerator\DefaultGenerator;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ManagerDomainService
{
    private const string NAME_PREFIX = 'cs';
    private const int PASSWORD_LENGTH = 16;

    public function __construct(
        private readonly AdminClientFactoryInterface $adminClientFactory,
        private readonly ClientFactoryInterface $clientFactory,
        private readonly DefaultGenerator $passwordGenerator,
        private readonly Serializer $serializer,
        private readonly Dispatcher $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AdminClientFactoryException
     * @throws ClientException
     */
    public function create(
        Environment $environment,
        Customer $customer,
    ): ManagerDomainDeployment {
        $managerDomainDeployment = new ManagerDomainDeployment();
        $managerDomainDeployment->domain_name
            = $managerDomainDeployment->account
            = $managerDomainDeployment->username
            = $this->generateUniqueName($environment);

        $this->logger->debug(
            sprintf('Creating VPS manager domain [%s]', $managerDomainDeployment->domain_name),
            [
                LoggingContextKeys::CUSTOMER_ID => $customer->id,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                LoggingContextKeys::META => [
                    'environment' => $environment->name,
                    'environment_id' => $environment->id,
                    'manager_domain_deployment_domain_name' => $managerDomainDeployment->domain_name,
                ],
            ]
        );

        $managerDomainDeployment->customer()->associate($customer);
        $managerDomainDeployment->environment()->associate($environment);
        $managerDomainDeployment->domain_id = $this->createDomain($managerDomainDeployment)->id;
        $managerDomainDeployment->save();

        $this->createAccount($managerDomainDeployment, $customer);

        return $managerDomainDeployment;
    }

    public function deleteDomain(ManagerDomainDeployment $managerDomainDeployment): bool
    {
        try {
            if (! $this->existsOnEnvironment($managerDomainDeployment->environment, $managerDomainDeployment)) {
                return true;
            }

            $client = $this->clientFactory->create($managerDomainDeployment);

            $deleteDomainJob = $client->deleteDomain((string) $managerDomainDeployment->domain_id, true);

            $cloudstackJob = $this->createCloudstackJob($deleteDomainJob, $managerDomainDeployment, [
                'job_id' => $deleteDomainJob->jobId,
            ]);

            $this->logger->info(
                sprintf(
                    'Cloudstack deleting domain [%s] with job ID [%s]',
                    $managerDomainDeployment->domain_id,
                    $cloudstackJob->job_id,
                ),
                [
                    LoggingContextKeys::META => [
                        'cloudstack_job' => $cloudstackJob->toArray(),
                    ],
                ]
            );

            $this->bus->dispatch(new DeleteDomainJob(
                $managerDomainDeployment,
                $cloudstackJob
            ));
        } catch (ClientFactoryException|ClientException|AdminClientFactoryException $exception) {
            $this->logger->error(
                'Something went wrong while trying to delete domain at Cloudstack',
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return false;
        }

        return true;
    }

    /**
     *
     * @throws ManagerDomainException
     * @throws ClientException
     * @throws ClientFactoryException
     */
    public function resetPassword(ManagerDomainDeployment $deployment): string
    {
        $client = $this->clientFactory->create($deployment);

        $users = $client->listUsers((string) $deployment->domain_id, $deployment->username);

        if (! $users->valid()) {
            throw new ManagerDomainException('User not found');
        }

        /** @var User $user */
        $user = $users->current();

        $password = $this->passwordGenerator->generatePassword(self::PASSWORD_LENGTH);
        $client->updateUser($user->id, $password);

        return $password;
    }

    /**
     * @param array<string, string|null> $extraFields
     */
    protected function createCloudstackJob(
        AsynchronousCloudstackResponse $asyncResponse,
        ManagerDomainDeployment $deployment,
        array $extraFields = []
    ): CloudstackJob {
        $jobData = $this->serializer->normalize($asyncResponse);
        Assert::isArray($jobData);

        /** @var array<string,mixed> $data */
        $data = $jobData;
        $data['account_id'] = $deployment->customer_id;
        $data = array_merge($data, $extraFields);

        return CloudstackJob::create($data);
    }

    /**
     * @throws AdminClientFactoryException
     * @throws ClientException
     */
    private function existsOnEnvironment(
        Environment $environment,
        ManagerDomainDeployment $deployment,
    ): bool {
        $client = $this->adminClientFactory->create($environment);

        $accounts = $client->listAccounts((string) $deployment->domain_id, $deployment->account);

        return iterator_count($accounts) > 0;
    }

    /**
     *
     * @throws ClientException
     * @throws Exception
     * @throws AdminClientFactoryException
     */
    private function generateUniqueName(Environment $environment): string
    {
        $client = $this->adminClientFactory->create($environment);

        do {
            $name = self::NAME_PREFIX . random_int(10_000_000, 99_999_999);
        } while (
            $client->listDomainChildren($environment->domain_id, $name)->valid()
            && ManagerDomainDeployment::withTrashed()->where('domain_name', $name)->exists()
        );

        return $name;
    }

    /**
     *
     * @throws ClientException
     * @throws AdminClientFactoryException
     */
    private function createDomain(ManagerDomainDeployment $deployment): Domain
    {
        $client = $this->adminClientFactory->create($deployment->environment);

        $domains = $client->listDomainChildren($deployment->environment->domain_id, $deployment->domain_name);

        if ($domains->valid()) {
            return $domains->current();
        }

        return $client->createDomain($deployment->environment->domain_id, $deployment->domain_name);
    }

    /**
     *
     * @throws AdminClientFactoryException
     * @throws ClientException
     */
    private function createAccount(ManagerDomainDeployment $deployment, Customer $customer): void
    {
        $client = $this->adminClientFactory->create($deployment->environment);

        $accounts = $client->listAccounts((string) $deployment->domain_id, $deployment->account);

        if ($accounts->valid()) {
            return;
        }

        $client->createAccount(
            domainId: $deployment->domain_id ?? '',
            username: $deployment->username,
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            email: $customer->email,
            password: $this->passwordGenerator->generatePassword(self::PASSWORD_LENGTH),
            roleId: $deployment->environment->default_role_id
        );
    }
}
