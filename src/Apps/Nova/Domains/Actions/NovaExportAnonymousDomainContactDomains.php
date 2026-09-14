<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainContactAnonymousHandle;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaExportAnonymousDomainContactDomains extends Action
{
    public function __construct(
        private readonly FilesystemManager $filesystemManager,
        private readonly TranslatorInterface $translator,
    ) {
        $this->standalone();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.export_anonymous_domain_contact_domains');
    }

    /**
     * @param Collection<int, DomainContact> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $anonHandles = DomainContactAnonymousHandle::pluck('handle');

        $csvContent = Subscription::query()
            ->whereHas(
                'domainDeployment.contactOwner.providers',
                fn (Builder $query) => $query->whereIn('external_contact', $anonHandles),
            )
            ->pluck('domain')
            ->implode("\n");

        $csvFileName = $this->storeCsv($csvContent);

        return ActionResponse::download(
            $csvFileName,
            '/export/' . $csvFileName,
        );
    }

    private function storeCsv(string $csvContent): string
    {
        $csvFileName = 'customer-anonymous-domain-contacts.csv';

        $this->filesystemManager->disk('private')->put('exports/' . $csvFileName, $csvContent);

        return $csvFileName;
    }
}
