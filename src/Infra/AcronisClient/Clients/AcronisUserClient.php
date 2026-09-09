<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\Clients;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\SaloonException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Infra\AcronisClient\Connectors\AcronisConnector;
use Waterfront\Infra\AcronisClient\DTO\Requests\Users\User;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\OneTimeToken;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\TenantUsers;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\User as UserResponse;
use Waterfront\Infra\AcronisClient\DTO\Responses\Users\UserAccessPolicies;
use Waterfront\Infra\AcronisClient\Exceptions\AcronisSerializerException;
use Waterfront\Infra\AcronisClient\Requests\User\GetTenantUsersRequest;
use Waterfront\Infra\AcronisClient\Requests\User\GetUserRequest;
use Waterfront\Infra\AcronisClient\Requests\User\GetUserSsoRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PostSetUserPasswordRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PostUserRequest;
use Waterfront\Infra\AcronisClient\Requests\User\PutUpdateUserAccessPoliciesRequest;
use Waterfront\Infra\AcronisClient\Serializers\AcronisSerializer;
use Waterfront\Support\Enums\LoggingContextKeys;

class AcronisUserClient
{
    public function __construct(
        private readonly AcronisConnector $connector,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function list(string $tenantId): TenantUsers
    {
        $response = $this->connector->send(new GetTenantUsersRequest(
            tenantId: $tenantId
        ));

        try {
            $users = AcronisSerializer::get()->deserialize($response->body(), TenantUsers::class, 'json');
        } catch (ExceptionInterface $exception) {
            throw new AcronisSerializerException(TenantUsers::class, $response->body(), $exception);
        }

        return $users;
    }

    /**
     * @throws AcronisSerializerException
     * @throws ExceptionInterface
     * @throws FatalRequestException
     * @throws RequestException
     */
    public function get(string $userId): UserResponse
    {
        $response = $this->connector->send(new GetUserRequest(
            userId: $userId
        ));

        try {
            $user = AcronisSerializer::get()->deserialize($response->body(), UserResponse::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(UserResponse::class, $response->body(), $exception);
        }

        return $user;
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     */
    public function create(User $payload): UserResponse
    {
        $response = $this->connector->send(new PostUserRequest(
            payload: $payload,
        ));

        try {
            $user = AcronisSerializer::get()->deserialize($response->body(), UserResponse::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(UserResponse::class, $response->body(), $exception);
        }

        return $user;
    }

    /**
     * @throws FatalRequestException
     * @throws SaloonException
     * @throws RequestException
     */
    public function updatePassword(string $userId, string $password): bool
    {
        try {
            $response = $this->connector->send(new PostSetUserPasswordRequest(
                userId: $userId,
                password: $password
            ));
        } catch (SaloonException $exception) {
            $this->logger->error(sprintf('Could not update Acronis password for user %s.', $userId), [
                LoggingContextKeys::EXCEPTION => $exception,
                LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::ACRONIS,
                LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::BACKUP,
            ]);
            throw $exception;
        }

        return $response->successful();
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws ExceptionInterface
     * @throws RequestException
     */
    public function updateUserAccessPolicies(string $userId, UserAccessPolicies $payload): UserAccessPolicies
    {
        $response = $this->connector->send(new PutUpdateUserAccessPoliciesRequest(
            userId: $userId,
            payload: $payload
        ));

        try {
            $userAccessPolicies = AcronisSerializer::get()->deserialize($response->body(), UserAccessPolicies::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(UserAccessPolicies::class, $response->body(), $exception);
        }

        return $userAccessPolicies;
    }

    /**
     * @throws AcronisSerializerException
     * @throws FatalRequestException
     * @throws RequestException
     * @throws ExceptionInterface
     */
    public function getSso(UuidInterface $userId, ?string $employeeUuid = null): OneTimeToken
    {
        $response = $this->connector->send(new GetUserSsoRequest(
            userId: $userId,
            employeeUuid: $employeeUuid
        ));

        try {
            $ott = AcronisSerializer::get()->deserialize($response->body(), OneTimeToken::class, 'json');
        } catch (RuntimeException $exception) {
            throw new AcronisSerializerException(OneTimeToken::class, $response->body(), $exception);
        }

        return $ott;
    }
}
