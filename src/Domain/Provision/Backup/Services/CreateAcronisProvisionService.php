<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Backup\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Provision\Backup\Acronis\Repositories\AcronisProviderRepository;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisClientFactoryException;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisCreateException;
use Waterfront\Domain\Provision\Backup\Exceptions\AcronisSetPasswordException;
use Waterfront\Domain\Provision\Backup\Repositories\AcronisBackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Repositories\BackupDeploymentRepository;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupDeploymentsFromMigrationRequest;
use Waterfront\Domain\Provision\Backup\Requests\CreateBackupRequest;
use Waterfront\Domain\Provision\Backup\Results\BackupCreateResult;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Support\LogContextBuilder;
use Waterfront\Infra\AcronisClient\Clients\AcronisClient;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\Contact as UserContact;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\User as CreateUser;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\PolicyItem;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\UserAccessPolicies;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Contact;
use Waterfront\Infra\AcronisClient\DTO\Tenants\Tenant;
use Waterfront\Infra\AcronisClient\DTO\Tenants\TenantPricingSettings;
use Waterfront\Infra\AcronisClient\Enums\RoleId;
use Waterfront\Infra\AcronisClient\Enums\Tenants\PricingMode;
use Waterfront\Infra\AcronisClient\Enums\Tenants\TenantType;
use Waterfront\Infra\AcronisClient\Enums\TrusteeType;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Factories\AcronisClientFactory;
use Waterfront\Support\Enums\LoggingContextKeys;

class CreateAcronisProvisionService
{
    private const int GENERATE_USERNAME_LENGTH = 8;

    private ?AcronisClient $acronisClient = null;

    public function __construct(
        private readonly AcronisClientFactory $acronisClientFactory,
        private readonly LoggerInterface $logger,
        private readonly BackupDeploymentRepository $backupRepository,
        private readonly AcronisBackupDeploymentRepository $acronisRepository,
        private readonly AcronisProviderRepository $acronisProviderRepository,
    ) {
    }

    public function createDeployment(CreateBackupDeploymentsFromMigrationRequest $provisionData): BackupCreateResult
    {
        $this->logger->debug(
            'Creating Acronis deployment for Migrations.',
            LogContextBuilder::for($provisionData)
                ->with(LoggingContextKeys::SUBSCRIPTION_UUID, $provisionData->tag)
                ->withMeta([
                    'tenantUuid' => $provisionData->tenantUuid,
                    'userUuid' => $provisionData->userUuid,
                    'providerId' => $provisionData->acronisProviderId,
                ])
                ->build(),
        );

        $requestId = $provisionData->requestId;

        $acronisDeployment = $this->backupRepository->findOrCreate(
            tag: $provisionData->tag,
            requestId: $requestId,
        );

        $this->acronisRepository->findOrCreate(
            acronisDeploymentId: $acronisDeployment->id,
            acronisProviderId: $provisionData->acronisProviderId,
            tenant: $provisionData->tenantUuid,
            user: $provisionData->userUuid,
        );

        return new BackupCreateResult(
            provisionData: $provisionData,
            provisionStatus: ProvisionStatus::SUCCESS,
        );
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     * @throws AcronisClientFactoryException
     */
    public function createTenant(CreateBackupRequest $createData): Tenant
    {
        $contact = new Contact(email: $createData->email);
        $contact->language = $createData->language->value;
        $contact->firstname = $createData->firstname;
        $contact->lastname = $createData->lastname;
        $contact->emailConfirmed = true;

        $tenant = new Tenant(
            name: sprintf('%s [%s]', $createData->email, $createData->tagUuid),
            parentId: $this->getDefaultClient()->tenantId->toString(),
            kind: TenantType::CUSTOMER,
            contact: $contact,
        );

        $tenant->customerId = $createData->email;
        $tenant->internalTag = $createData->tagUuid->toString();
        $tenant->language = $createData->language->value;

        $this->logger->info(
            'Creating Acronis tenant.',
            LogContextBuilder::for($createData)
                ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                ->withMeta(['client_tenant_id' => $this->getDefaultClient()->tenantId->toString()])
                ->build(),
        );

        return $this->getDefaultClient()->tenantClient->create($tenant);
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisClientFactoryException
     */
    public function findOrCreateUser(string $tenantId, CreateBackupRequest $createData): User
    {
        $users = $this->getDefaultClient()->userClient->list($tenantId);
        $amountOfUsers = count($users->items);

        if ($amountOfUsers === 0) {
            return $this->createUser($tenantId, $createData);
        }

        if ($amountOfUsers > 1) {
            $this->logger->info(
                'Found more than 1 user for an Acronis tenant. This should not be the case. Using the 1st user.',
                LogContextBuilder::for($createData)
                    ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                    ->withMeta([
                        'tenant_id' => $tenantId,
                        'users' => $users->items,
                    ])
                    ->build(),
            );
        }

        $userId = $users->items[0];

        $this->logger->info(
            'Fetching existing Acronis user for tenant.',
            LogContextBuilder::for($createData)
                ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                ->withMeta([
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'client_tenant_id' => $this->getDefaultClient()->tenantId->toString(),
                ])
                ->build(),
        );

        return $this->getDefaultClient()->userClient->get($userId);
    }

    /**
     * Use the given password or generate a new one for the user.
     *
     * @see https://care.acronis.com/s/article/Acronis-Cyber-Protect-Cloud-Password-Policy-Management
     */
    public function setPassword(string $userId, ?string $password): string
    {
        $password ??= Str::password(length: 16, symbols: false);

        try {
            $this->getDefaultClient()->userClient->updatePassword($userId, $password);
        } catch (SaloonException|AcronisClientFactoryException $exception) {
            throw new AcronisSetPasswordException(
                message: sprintf('Error during password set of user %s', $userId),
                previous: $exception,
            );
        }

        return $password;
    }

    public function updateAccessPolicies(
        string $userId,
        string $tenantId,
        CreateBackupRequest $createData,
    ): ?UserAccessPolicies {
        $this->logger->info(
            sprintf('Updating access policies for user [%s] in tenant [%s].', $userId, $tenantId),
            LogContextBuilder::for($createData)
                ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                ->withMeta([
                    'user_id' => $userId,
                    'tenant_id' => $tenantId,
                    'client_tenant_id' => $this->getDefaultClient()->tenantId->toString(),
                ])
                ->build(),
        );

        $userPoliciesResponse = null;

        try {
            $policies = [
                new PolicyItem(
                    tenantId: $tenantId,
                    trusteeId: $userId,
                    trusteeType: TrusteeType::USER,
                    roleId: RoleId::BACKUP_USER,
                    version: time(),
                ),
                new PolicyItem(
                    tenantId: $tenantId,
                    trusteeId: $userId,
                    trusteeType: TrusteeType::USER,
                    roleId: RoleId::PROTECTION_ADMIN,
                    version: time(),
                ),
            ];

            $accessPolicies = new UserAccessPolicies(
                items: $policies,
                timestamp: CarbonImmutable::now()->toIso8601String(),
            );

            $userPoliciesResponse = $this->getDefaultClient()->userClient->updateUserAccessPolicies(
                $userId,
                $accessPolicies,
            );
        } catch (SaloonException|ExceptionInterface|AcronisClientFactoryException $exception) {
            $this->logger->warning(
                sprintf('Error during Acronis updateAccessPolicies for tenant [%s] user [%s]', $tenantId, $userId),
                LogContextBuilder::for($createData)
                    ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                    ->withException($exception)
                    ->withMeta([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                    ])
                    ->build(),
            );
        }

        return $userPoliciesResponse;
    }

    public function updatePricingToProduction(string $tenantId): ?TenantPricingSettings
    {
        $this->logger->info(
            sprintf('Updating pricing to production for tenant %s.', $tenantId),
            [
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                LoggingContextKeys::META => [
                    'tenant_id' => $tenantId,
                    'client_tenant_id' => $this->getDefaultClient()->tenantId->toString(),
                ],
            ],
        );

        $tenantPricingSetting = null;

        try {
            $retrievedPricingSettings = $this->getDefaultClient()->tenantClient->getPricingSettings($tenantId);

            $retrievedPricingSettings->mode = PricingMode::PRODUCTION;

            $this->getDefaultClient()->tenantClient->updatePricingSettings(
                tenantId: $tenantId,
                payload: $retrievedPricingSettings,
            );
        } catch (SaloonException|ExceptionInterface|AcronisClientFactoryException $exception) {
            $this->logger->warning(
                sprintf('Error during Acronis updatePricingToProduction for tenant [%s]', $tenantId),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'tenant_id' => $tenantId,
                    ],
                ],
            );
        }

        return $tenantPricingSetting;
    }

    /**
     * @throws AcronisCreateException
     */
    public function storeDeployments(
        int $requestId,
        UuidInterface $tenant,
        UuidInterface $user,
    ): void {
        $defaultProvider = $this->acronisProviderRepository->getDefault();
        if ($defaultProvider === null) {
            throw new AcronisCreateException(
                'Could not retrieve default Acronis provider, unable to store deployments.',
            );
        }

        $this->acronisRepository->create(
            acronisDeployment: $this->backupRepository->create($requestId),
            acronisProviderId: $defaultProvider->id,
            tenant: $tenant,
            user: $user,
        );
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     * @throws AcronisClientFactoryException
     */
    private function createUser(string $tenantId, CreateBackupRequest $createData): User
    {
        $this->logger->info(
            'Creating Acronis user for tenant.',
            LogContextBuilder::for($createData)
                ->with(LoggingContextKeys::PROVISIONING_REQUEST_ID, $createData->tagUuid)
                ->withMeta([
                    'tenant_id' => $tenantId,
                    'client_tenant_id' => $this->getDefaultClient()->tenantId->toString(),
                ])
                ->build(),
        );

        $userContact = new UserContact(
            firstname: $createData->firstname,
            lastname: $createData->lastname,
            email: $createData->email,
            emailConfirmed: true,
        );

        $createUserPayload = new CreateUser(
            tenantId: $tenantId,
            login: $createData->username ?? Str::random(self::GENERATE_USERNAME_LENGTH),
            enabled: true,
            contact: $userContact,
        );

        $createUserPayload->language = $createData->language->value;

        return $this->getDefaultClient()->userClient->create($createUserPayload);
    }

    /**
     * This method provides the default client in Runtime because the database
     * fields might not contain a default row yet which during boot can lead
     * to an exception when using DI. We cache the client to prevent queries.
     *
     * @throws AcronisClientFactoryException
     */
    private function getDefaultClient(): AcronisClient
    {
        return $this->acronisClient ??= $this->acronisClientFactory->getDefault();
    }
}
