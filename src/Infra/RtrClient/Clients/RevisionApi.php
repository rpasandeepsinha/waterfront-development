<?php

declare(strict_types=1);

namespace Waterfront\Infra\RtrClient\Clients;

use Carbon\CarbonImmutable;
use DateMalformedStringException;
use RealtimeRegister\Api\AbstractApi;
use Waterfront\Infra\RtrClient\DTO\Revision;

class RevisionApi extends AbstractApi
{
    /**
     * Revision API that is only available for whitelisted IPs by RTR
     * Endpoint is found at /v2/domains/<domain>/revisions using a GET method.
     *
     * @see https://yourhosting.slack.com/archives/C0AULNN8J68/p1780567073691659
     *
     * @throws DateMalformedStringException
     *
     * @return Revision[]
     */
    public function listRevisions(
        string $domain,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        $query = $this->processListQuery(
            limit: null,
            offset: null,
            search: null,
            parameters: [
                'from' => $from?->format('Y-m-d\TH:i:s\Z'),
                'to' => $to?->format('Y-m-d\TH:i:s\Z'),
            ],
        );

        $response = $this->client->get(sprintf('v2/domains/%s/revisions', $domain), $query);
        $responseRevisions = $response->json();
        $revisions = [];

        foreach ($responseRevisions as $revision) {
            $revisions[] = Revision::fromArray($revision);
        }

        return $revisions;
    }
}
