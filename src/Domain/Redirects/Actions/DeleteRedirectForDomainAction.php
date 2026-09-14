<?php

declare(strict_types=1);

namespace Waterfront\Domain\Redirects\Actions;

use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class DeleteRedirectForDomainAction
{
    public function __construct(
        private readonly RedirectService $redirectService,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    /**
     * If the old record pointed towards the REDIRECTS server and the new record DOES NOT we should remove the existing redirects for $domain.
     *
     * @param array<string>      $oldRecord
     * @param array<string>|null $newRecord Is null when a record is being deleted
     */
    public function execute(Subscription $subscription, array $oldRecord, ?array $newRecord = null): void
    {
        /**
         * Use the domain or subdomain from the (to be updated/deleted) DNS-record.
         */
        $domain = $oldRecord['name'] ?? null;

        if (! is_string($domain)) {
            return;
        }

        if ($newRecord === null) {
            if ($this->isRedirectManagedDnsRecord($oldRecord) && $this->isPointingToRedirectService($oldRecord)) {
                $this->deleteRedirects($subscription, $domain);
            }

            return;
        }

        if ($this->isRedirectManagedDnsRecord($oldRecord) && ! $this->isRedirectManagedDnsRecord($newRecord)) {
            $this->deleteRedirects($subscription, $domain);

            return;
        }

        if (! $this->hasContentChanged($oldRecord, $newRecord)) {
            return;
        }

        if ($this->isPointingToRedirectService($oldRecord) && ! $this->isPointingToRedirectService($newRecord)) {
            $this->deleteRedirects($subscription, $domain);
        }
    }

    private function deleteRedirects(Subscription $subscription, string $domain): void
    {
        $this->redirectService->deleteRedirect($subscription, $domain);
    }

    /** @param array<string> $record */
    private function isRedirectManagedDnsRecord(array $record): bool
    {
        $type = $record['type'] ?? null;

        return in_array(
            $type,
            [
                DnsRecordType::CNAME->value,
                DnsRecordType::ALIAS->value,
            ],
            true,
        );
    }

    /** @param array<string> $record */
    private function isPointingToRedirectService(array $record): bool
    {
        return $this->configuration->getAsString('caddyclient.redirect_dns') === ($record['content'] ?? null);
    }

    /**
     * @param array<string> $oldRecord
     * @param array<string> $newRecord
     */
    private function hasContentChanged(array $oldRecord, array $newRecord): bool
    {
        return ($oldRecord['content'] ?? null) !== ($newRecord['content'] ?? null);
    }
}
