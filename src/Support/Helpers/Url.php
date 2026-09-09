<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use Illuminate\Http\Request;
use Waterfront\Infra\Configuration\Configuration;

class Url
{
    public function __construct(private readonly Configuration $configuration)
    {
    }

    public function getSubdomain(Request $request): ?string
    {
        $root = $request->root();

        $rootStripped = $this->stripProtocol($root);
        $rootStripped = $this->stripDomain($rootStripped);

        $subdomain = str_replace('.', '', $rootStripped);

        if ($subdomain === '') {
            return null;
        }

        return $subdomain;
    }

    private function stripProtocol(string $string): string
    {
        return str_replace($this->configuration->getAsString('constants.general.protocol'), '', $string);
    }

    private function stripDomain(string $string): string
    {
        return str_replace($this->configuration->getAsString('constants.general.domain'), '', $string);
    }
}
