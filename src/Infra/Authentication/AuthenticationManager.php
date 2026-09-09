<?php

declare(strict_types=1);

namespace Waterfront\Infra\Authentication;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use SandwaveIo\LighthouseAuthBase\Identity\KratosIdentity;
use SandwaveIo\LighthouseAuthBase\Identity\Traits;
use SandwaveIo\LighthouseAuthBase\Service\IdentitySchemaConverter;
use ValueError;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Repositories\CustomerRepository;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;
use Waterfront\Infra\Authentication\DTO\AuthenticatedUnregisteredCustomer;
use Webmozart\Assert\Assert;
use Webmozart\Assert\InvalidArgumentException;

class AuthenticationManager
{
    protected AuthenticatedCustomer|AuthenticatedSystem|AuthenticatedUnregisteredCustomer|AuthenticatedEmployee|null $authenticatedSubject = null;

    private readonly UuidInterface $consoleIdentityUuid;

    public function __construct(
        private readonly OathKeeperService $oathKeeperService,
        private readonly LoggerInterface $logger,
        private readonly AuthManager $authManager,
        private readonly IdentitySchemaConverter $identitySchemaConverter,
        private readonly CustomerRepository $customerRepository,
        string $consoleIdentityUuid,
    ) {
        $this->consoleIdentityUuid = Uuid::fromString($consoleIdentityUuid);
    }

    /**
     * @throws AuthenticationException
     */
    public function getAuthenticatedSubject(): AuthenticatedCustomer|AuthenticatedSystem|AuthenticatedUnregisteredCustomer|AuthenticatedEmployee
    {
        if ($this->authenticatedSubject === null) {
            throw new AuthenticationException('No authenticated subject set');
        }

        return $this->authenticatedSubject;
    }

    public function getAuthenticatedEmployee(): AuthenticatedEmployee
    {
        if ($this->authenticatedSubject === null) {
            throw new AuthenticationException('No authenticated subject set');
        }

        if (! $this->authenticatedSubject instanceof AuthenticatedEmployee) {
            throw new AuthorizationException('Authenticated subject is not an employee');
        }

        return $this->authenticatedSubject;
    }

    public function getAuthenticatedSystem(): AuthenticatedSystem
    {
        if ($this->authenticatedSubject === null) {
            throw new AuthenticationException('No authenticated subject set');
        }

        if (! $this->authenticatedSubject instanceof AuthenticatedSystem) {
            throw new AuthorizationException('Authenticated subject is not a system');
        }

        return $this->authenticatedSubject;
    }

    /**
     * @throws AuthenticationException|AuthorizationException
     */
    public function getAuthenticatedCustomer(): AuthenticatedCustomer
    {
        if ($this->authenticatedSubject === null) {
            throw new AuthenticationException('No authenticated subject set');
        }

        if (! $this->authenticatedSubject instanceof AuthenticatedCustomer) {
            // NOTE: the frontend relies on this exact error message. See https://git.sandwave.io/sandwave/coast/-/merge_requests/1848.
            throw new AuthorizationException('Authenticated subject is not a customer');
        }

        return $this->authenticatedSubject;
    }

    /**
     * @throws AuthenticationException
     * @throws LogicException
     */
    public function handleRequest(Request $request): void
    {
        $token = $this->retrieveAndRemoveAuthorizationToken($request);
        $jwt = $this->oathKeeperService->retrieveValidatedJwt($token);

        if (! is_array($jwt)) {
            throw new AuthenticationException('Unable to get validated JWT');
        }

        try {
            $identitySchema = $this->identitySchemaConverter->convert($jwt);
        } catch (InvalidArgumentException|ValueError $exception) {
            $this->logger->error(sprintf('Error while converting the identity schema: %s', $exception->getMessage()));

            throw new AuthenticationException('Unable to convert the identity schema.');
        }

        $verified = count(array_filter($identitySchema->verifiableAddresses ?? [], fn ($address) => $address->value === $identitySchema->traits?->email && $address->verified === true)) > 0;

        if ($identitySchema->schemaId === SchemaId::EMPLOYEE && $request->header('x-customer-id') === null) {
            $this->authenticatedSubject = new AuthenticatedEmployee($identitySchema, $verified);

            $this->authManager->login($this->authenticatedSubject);
            return;
        }

        $customerNumber = $this->retrieveCorrectCustomerNumber($identitySchema, $request);

        if ($customerNumber !== null) {
            $customer = $this->customerRepository->findByCustomerNumber($customerNumber);

            if ($customer instanceof Customer) {
                $this->authenticatedSubject = new AuthenticatedCustomer($customer, $identitySchema, $verified);

                $this->authManager->login($customer);
                return;
            }

            throw new AuthenticationException(
                sprintf(
                    'No customer found for customer number %d',
                    $customerNumber
                )
            );
        }

        if ($identitySchema->schemaId === SchemaId::CUSTOMER) {
            $this->authenticatedSubject = new AuthenticatedUnregisteredCustomer($identitySchema);
            return;
        }

        if ($identitySchema->schemaId === SchemaId::SYSTEM) {
            $this->authenticatedSubject = new AuthenticatedSystem($identitySchema);
            return;
        }

        throw new LogicException(sprintf('Unsupported schema ID: %s', $identitySchema->schemaId->value));
    }

    public function handleConsole(): void
    {
        $identitySchema = new KratosIdentity(
            $this->consoleIdentityUuid,
            SchemaId::SYSTEM,
            'active',
            null,
            new Traits('system@sandwave.io', null),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        );
        $this->authenticatedSubject = new AuthenticatedSystem($identitySchema);
    }

    /**
     * @throws AuthenticationException
     */
    private function retrieveAndRemoveAuthorizationToken(Request $request): string
    {
        $bearer = $request->header('authorization');

        if (! is_string($bearer)) {
            throw new AuthenticationException('The bearer request header is mandatory, but currently missing.');
        }

        $request->headers->remove('authorization');

        return Str::replaceFirst('Bearer ', '', $bearer);
    }

    /**
     * @throws AuthenticationException
     */
    private function retrieveCorrectCustomerNumber(KratosIdentity $identitySchema, Request $request): ?int
    {
        $customerHeader = $request->header('x-customer-id');
        $request->headers->remove('x-customer-id');

        if ($identitySchema->schemaId === SchemaId::EMPLOYEE) {
            try {
                Assert::integerish($customerHeader);
            } catch (InvalidArgumentException) {
                throw new AuthenticationException('Invalid customer number in customer header.');
            }
            return (int) $customerHeader;
        }

        $customerRelationItem = array_filter($identitySchema->metadataPublic->customerRelations ?? [], fn ($data) => $data->customerNumber === (int) $customerHeader);

        if (count($customerRelationItem) === 1) {
            $customerRelationItem = array_values($customerRelationItem);
            return $customerRelationItem[0]->customerNumber;
        }

        if (is_array($identitySchema->metadataPublic?->customerRelations) && count($identitySchema->metadataPublic->customerRelations) > 0) {
            return $identitySchema->metadataPublic->customerRelations[0]->customerNumber;
        }

        if (is_array($identitySchema->metadataPublic?->customerNumbers) && count($identitySchema->metadataPublic->customerNumbers) > 0) {
            return $identitySchema->metadataPublic->customerNumbers[0];
        }

        return null;
    }
}
