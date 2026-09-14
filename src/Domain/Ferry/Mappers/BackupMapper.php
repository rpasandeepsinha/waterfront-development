<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use UnexpectedValueException;
use Waterfront\Domain\Ferry\Dto\Backup\BackupMigrationPayload;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class BackupMapper
{
    /**
     * @param array<string, mixed> $technicalPayloads
     */
    public function mapSubscriptionsWithBackups(
        Subscription $subscription,
        array $technicalPayloads,
    ): BackupMigrationPayload {
        $migratedSubscription = $subscription->migratedSubscriptions->firstOrFail();

        /** @var string $referenceSubscriptionId */
        $referenceSubscriptionId = $migratedSubscription->reference_subscription_id;

        foreach ($technicalPayloads as $technicalPayload) {
            if (
                is_array($technicalPayload)
                && array_key_exists('reference_subscription_id', $technicalPayload)
                && $technicalPayload['reference_subscription_id'] === $referenceSubscriptionId
            ) {
                /** @var array<string, string> $details */
                $details = $technicalPayload['backup_data'];

                $buTenantUuid = $details['bu_tenant_uuid'];
                $customerTenantUuid = $details['customer_tenant_uuid'];
                $userUuid = $details['user_uuid'];

                return new BackupMigrationPayload(
                    referenceSubscriptionId: $referenceSubscriptionId,
                    buTenantUuid: $buTenantUuid,
                    customerTenantUuid: $customerTenantUuid,
                    userUuid: $userUuid,
                );
            }
        }

        throw new UnexpectedValueException(sprintf(
            'No technical payload found for subscription with reference_subscription_id "%s"',
            $referenceSubscriptionId,
        ));
    }
}
