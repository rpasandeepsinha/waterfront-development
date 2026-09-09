<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Tenants;

use Symfony\Component\Serializer\Attribute\Groups;
use Waterfront\Infra\AcronisClient\Enums\Tenants\ContactType;

class Contact
{
    public ?string $id = null;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    #[Groups(['put'])]
    public ?string $tenantId;

    #[Groups(['put'])]
    public ?string $userId;

    /**
     * @var list<ContactType>|null
     */
    #[Groups(['put'])]
    public ?array $types;

    #[Groups(['create', 'put'])]
    public ?string $address1;

    #[Groups(['create', 'put'])]
    public ?string $address2;

    #[Groups(['create', 'put'])]
    public ?string $country;

    #[Groups(['create', 'put'])]
    public ?string $state;

    #[Groups(['create', 'put'])]
    public ?string $city;

    #[Groups(['create', 'put'])]
    public ?string $zipcode;

    #[Groups(['create', 'put'])]
    public ?string $phone;

    #[Groups(['create', 'put'])]
    public ?string $firstname;

    #[Groups(['create', 'put'])]
    public ?string $lastname;

    #[Groups(['create', 'put'])]
    public ?string $title;

    #[Groups(['create', 'put'])]
    public ?string $website;

    #[Groups(['create', 'put'])]
    public ?string $industry;

    #[Groups(['create', 'put'])]
    public ?string $organizationSize;

    #[Groups(['create', 'put'])]
    public ?bool $emailConfirmed;

    #[Groups(['create', 'put'])]
    public ?string $aan;

    #[Groups(['create', 'put'])]
    public ?string $language;

    #[Groups(['create', 'put'])]
    public ?string $fax;

    public function __construct(
        #[Groups(['create', 'put'])]
        public ?string $email = null,
    ) {
    }
}
