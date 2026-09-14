<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use RealtimeRegister\Exceptions\RealtimeRegisterClientException;
use Waterfront\Domain\Domains\Models\DomainContactAnonymousHandle;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchAnonymousDomainContactFromRtr extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RtrService $rtrService,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_anonymous_domain_contact_from_rtr');
    }

    /**
     * @param Collection<int, DomainContactAnonymousHandle> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var DomainContactAnonymousHandle $anonymousHandle */
        $anonymousHandle = $models->first();

        try {
            $remoteContact = $this->rtrService->retrieveCustomerHandle($anonymousHandle->handle);

            $data = $remoteContact->toArray();
        } catch (RealtimeRegisterClientException $exception) {
            $data = [
                'exception' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $title = sprintf(
            'Fetched anonymous domain contact with handle {%s} from Rtr with response:',
            $anonymousHandle->handle,
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_PRETTY_PRINT),
        ]);
    }
}
