<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Services\RedirectsDatabase;

use Illuminate\Container\Container;
use Waterfront\Infra\Common\PublicSuffixList;

class Redirect
{
    public function __construct(
        public int $customerId,
        public string $domainBody,
        public string $tld,
        public string $source,
        public string $destination,
        public string $type,
    ) {
    }

    /** @return array{string|null, string|null, string|null} */
    public static function parseHost(string $host): array
    {
        /** @var PublicSuffixList $rules */
        $rules = Container::getInstance()->make(PublicSuffixList::class);
        $domain = $rules->getRules()->resolve($host);
        $subdomain = $domain->subDomain()->toString();
        $extension = $domain->suffix()->toString();
        $body = str_replace(".{$extension}", '', $domain->registrableDomain()->toString());

        return [$subdomain, $body, $extension];
    }
}
