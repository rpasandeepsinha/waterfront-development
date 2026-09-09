<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Exceptions;

use Exception;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

class NotEligibleForMigrationException extends Exception
{
    public static function administrativeStatusIncorrect(string $actualStatus): NotEligibleForMigrationException
    {
        return new self(sprintf('The administrative status for this subscription (%s) is not eligible for migration', $actualStatus));
    }

    public static function technicalStatusIncorrect(string|null $actualStatus): NotEligibleForMigrationException
    {
        return new self(sprintf('The technical status for this subscription (%s) is not eligible for migration', $actualStatus ?? '<null>'));
    }

    public static function incorrectProduct(string $productSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The product for this subscription (%s) is not eligible for this kind of migration', $productSlug));
    }

    public static function missingDomainSubscription(): NotEligibleForMigrationException
    {
        return new self('No domain subscription could be found for this subscription');
    }

    public static function missingSslDeployment(): NotEligibleForMigrationException
    {
        return new self('No Ssl Deployment could be found for this subscription');
    }

    public static function missingHostingSubscription(): NotEligibleForMigrationException
    {
        return new self('No hosting deployment could be found for this subscription');
    }

    public static function missingResellerHostingSubscription(): NotEligibleForMigrationException
    {
        return new self('No reseller hosting deployment could be found for this subscription');
    }

    public static function incorrectDomainProvider(ProviderSlug $providerSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The domain provider for this subscription (%s) is not eligible for this kind of migration', $providerSlug->value));
    }

    public static function incorrectHostingProvider(string|null $providerSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The hosting provider for this subscription (%s) is not eligible for this kind of migration', $providerSlug ?? '<null>'));
    }

    public static function incorrectResellerHostingProvider(string|null $providerSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The reseller hosting provider for this subscription (%s) is not eligible for this kind of migration', $providerSlug ?? '<null>'));
    }

    public static function incorrectMailOnlyProvider(string|null $providerSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The mail only provider (%s) for this subscription is not eligible for this kind of migration', $providerSlug ?? '<null>'));
    }

    public static function incorrectSitebuilderProvider(string|null $mailProviderSlug, string|null $sitebuilderProviderSlug): NotEligibleForMigrationException
    {
        return new self(sprintf(
            'The mail only provider (%s) or the sitebuilder provider (%s) for this subscription is not eligible for this kind of migration',
            $mailProviderSlug ?? '<null>',
            $sitebuilderProviderSlug ?? '<null>'
        ));
    }

    public static function incorrectSslProvider(ProviderSlug $providerSlug): NotEligibleForMigrationException
    {
        return new self(sprintf('The SSL provider for this subscription (%s) is not eligible for this kind of migration', $providerSlug->value));
    }
}
