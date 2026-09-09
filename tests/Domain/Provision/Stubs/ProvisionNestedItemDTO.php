<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Stubs;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Waterfront\Domain\Provision\Backup\Acronis\Enums\Language;

class ProvisionNestedItemDTO
{
    #[SerializedName('user_email')]
    public string $email = '';

    public Language $language = Language::ENGLISH;
}
