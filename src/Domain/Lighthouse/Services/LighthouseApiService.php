<?php

declare(strict_types=1);

namespace Waterfront\Domain\Lighthouse\Services;

use JsonException;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\Identity;
use Symfony\Component\HttpFoundation\Request;
use Waterfront\Domain\Lighthouse\DTO\IdentityCreatedResponseDTO;
use Waterfront\Domain\Lighthouse\DTO\IdentityState;
use Waterfront\Domain\Lighthouse\Exceptions\LighthouseException;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;
use Waterfront\Domain\Lighthouse\Helper\RequestHelper;
use Waterfront\Domain\Lighthouse\Serialize\IdentitySerializerFactory;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class LighthouseApiService
{
    public function __construct(
        private readonly ConfigurationInterface $configuration,
        private readonly RequestHelper $requestHelper,
        private readonly IdentitySerializerFactory $identitySerializerFactory,
    ) {
    }

    /**
     * @throws LighthouseException
     * @throws JsonException
     */
    public function createKratosIdentity(string $email, int $customerNumber): IdentityCreatedResponseDTO
    {
        $response = $this->requestHelper->request(
            $this->configuration->getAsString('lighthouse.api_url') . '/kratos/identities',
            [
                'email' => $email,
                'schema' => SchemaId::CUSTOMER->value,
                'customers' => [
                    $customerNumber,
                ],
                'business_relations' => [
                    'waterfront',
                ],
            ],
            Request::METHOD_POST,
        );

        return $this->identitySerializerFactory->get()->deserialize(
            $response->body(),
            IdentityCreatedResponseDTO::class,
            'json',
        );
    }

    public function updateKratosIdentity(Identity $identity): Identity
    {
        $response = $this->requestHelper->request(
            $this->configuration->getAsString('lighthouse.api_url') . "/kratos/identities/{$identity->id}",
            [
                'email' => $identity->traits->email,
                'active' => $identity->state === IdentityState::ACTIVE->value,
                'customers' => $identity->metadataPublic->customerNumbers ?? [],
                'business_relations' => $identity->metadataPublic->businessRelations ?? [],
            ],
            Request::METHOD_PUT,
        );

        return $this->identitySerializerFactory->get()->deserialize($response->body(), Identity::class, 'json');
    }

    /**
     * @throws LighthouseException
     * @throws ResourceNotFoundException
     * @throws JsonException
     */
    public function getKratosIdentityByIdentifier(string $identifier): Identity
    {
        $url = sprintf(
            '%s/%s/%s',
            $this->configuration->getAsString('lighthouse.api_url'),
            'kratos/identities',
            $identifier,
        );

        $response = $this->requestHelper->request(
            $url,
            [],
            Request::METHOD_GET,
        );

        return $this->identitySerializerFactory->get()->deserialize($response->body(), Identity::class, 'json');
    }

    /**
     * @throws LighthouseException
     * @throws ResourceNotFoundException
     * @throws JsonException
     *
     * @return Identity[]
     */
    public function getKratosIdentitiesByCustomerNumber(int $customerNumber): array
    {
        $url = sprintf(
            '%s/%s',
            $this->configuration->getAsString('lighthouse.api_url'),
            'kratos/identities/search',
        );

        $response = $this->requestHelper->request(
            $url,
            [
                'customerNumber' => $customerNumber,
            ],
            Request::METHOD_GET,
        );

        /** @var Identity[] $identitiesResponse */
        $identitiesResponse = $this->identitySerializerFactory->get()->deserialize(
            $response->body(),
            Identity::class . '[]',
            'json',
        );

        return $identitiesResponse;
    }

    /**
     * @throws LighthouseException
     * @throws JsonException
     */
    public function detachIdentityForBusinessUnit(UuidInterface $uuid, int $customerNumber): void
    {
        $url = sprintf(
            '%s/%s/%s/%s',
            $this->configuration->getAsString('lighthouse.api_url'),
            '/kratos/identities',
            $uuid,
            '/detach-customer',
        );

        $this->requestHelper->request(
            $url,
            [
                'customerNumber' => $customerNumber,
            ],
            Request::METHOD_POST,
        );
    }
}
