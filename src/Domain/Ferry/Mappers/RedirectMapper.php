<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Waterfront\Domain\Ferry\Dto\Redirects\RedirectMigrationPayload;
use Waterfront\Domain\Ferry\Dto\Redirects\RedirectTechnicalPayload;
use Waterfront\Domain\Ferry\Serializers\FerrySerializerFactory;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class RedirectMapper
{
    /**
     * @param Collection<int, Subscription>     $subscriptions
     * @param array<int, array<string, string>> $technicalPayloads
     *
     * @return array<int, RedirectMigrationPayload>
     */
    public function mapSubscriptionsWithRedirects(
        Collection $subscriptions,
        array $technicalPayloads,
    ): array {
        $mappedPayloads = [];
        $technicalRedirects = $this->mapTechnicalRedirects($technicalPayloads);

        foreach ($subscriptions as $subscription) {
            foreach ($technicalRedirects as $technicalRedirect) {
                $sourceMatchesDomain =
                    $technicalRedirect->source === $subscription->domain
                    || Str::contains($technicalRedirect->source, '.' . $subscription->domain);

                if ($sourceMatchesDomain) {
                    $mappedPayloads[] = new RedirectMigrationPayload(
                        subscription: $subscription,
                        redirectTechnicalPayload: $technicalRedirect,
                    );
                }
            }
        }

        return $mappedPayloads;
    }

    /**
     * @param array<int, array<string, string>> $payloads
     *
     * @return RedirectTechnicalPayload[]
     */
    private function mapTechnicalRedirects(array $payloads): array
    {
        /** @var RedirectTechnicalPayload[] $payloads */
        $payloads = FerrySerializerFactory::getSerializer()->denormalize(
            $payloads,
            RedirectTechnicalPayload::class . '[]',
        );

        return $payloads;
    }
}
