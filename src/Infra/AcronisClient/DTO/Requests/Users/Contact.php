<?php

declare(strict_types=1);

namespace Waterfront\Infra\AcronisClient\DTO\Requests\Users;

use Symfony\Component\Serializer\Attribute\Groups;

#[Groups(['create'])]
class Contact
{
    public function __construct(
        public string $firstname,
        public string $lastname,
        public string $email,
        public ?string $address1 = null,
        public ?string $address2 = null,
        public ?string $country = null,
        public ?string $state = null,
        public ?string $city = null,
        public ?string $zipcode = null,
        public ?string $phone = null,
        public ?string $title = null,
        public ?string $website = null,
        public ?string $industry = null,
        public ?string $organizationSize = null,
        public ?bool $emailConfirmed = null,
        public ?string $aan = null,
    ) {
    }
}
