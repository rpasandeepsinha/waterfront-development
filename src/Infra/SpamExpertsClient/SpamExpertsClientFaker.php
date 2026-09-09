<?php

declare(strict_types=1);

namespace Waterfront\Infra\SpamExpertsClient;

use GuzzleHttp\Psr7\Response as HttpResponse;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Infra\SpamExpertsClient\Exceptions\SpamexpertsSsoException;
use Waterfront\Infra\SpamExpertsClient\Messages\RemoveDomain\Response as RemoveDomainResponse;

class SpamExpertsClientFaker extends SpamExpertsClient
{
    public function addDomain(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): void
    {
    }

    public function removeDomain(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): RemoveDomainResponse
    {
        $message = "SUCCESS: Domain '" . $domain . "' removed";
        $httpResponse = new HttpResponse(200, [], $message);

        return new RemoveDomainResponse($httpResponse);
    }

    public function generateSsoToken(string $domain, ?SpamExpertsCluster $spamExpertsCluster = null): string
    {
        if ($domain === 'exception.com') {
            throw new SpamexpertsSsoException('example-exception');
        }
        return "my-token-for-domain-$domain";
    }
}
