<?php

declare(strict_types=1);

namespace Waterfront\Infra\MicrosoftOnlineClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class OpenIdConfiguration
{
    public function __construct(
        #[SerializedName('authorization_endpoint')]
        public string $authorizationEndpoint,
    ) {
    }
}
