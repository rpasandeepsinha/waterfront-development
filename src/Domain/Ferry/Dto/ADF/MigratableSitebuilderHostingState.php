<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Dto\ADF;

use Waterfront\Domain\Ferry\Enums\MigrationStep;

readonly class MigratableSitebuilderHostingState implements MigrationTypeADFPayload
{
    public function __construct(
        public MigrationStep $migrationStep,
        public string $referenceName,
        public ?string $domain,
        public string $migrationSubscriptionReferenceId,
        public ?string $sitebuilderHostname,
        public ?string $mailOnlyHostname,
        public ?string $mailOnlyUsername,
        public ?int $basekitUserRef,
        public ?int $basekitSiteRef,
        public string $driver,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->migrationStep->value,
            'domain' => $this->domain,
            'reference_subscription_id' => $this->migrationSubscriptionReferenceId,
            'reference_name' => $this->referenceName,
            'basekit_user_ref' => $this->basekitUserRef,
            'basekit_site_ref' => $this->basekitSiteRef,
            'sitebuilder_hostname' => $this->sitebuilderHostname,
            'mail_only_hostname' => $this->mailOnlyHostname,
            'mail_only_username' => $this->mailOnlyUsername,
            'driver' => $this->driver,
        ];
    }
}
