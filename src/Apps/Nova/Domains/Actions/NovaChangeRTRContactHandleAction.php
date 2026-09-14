<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Domains\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaChangeRTRContactHandleAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.change_rtr_contact_handle');
    }

    /**
     * @param Collection<int, DomainContact> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $contact = $models->first();

        assert($contact instanceof DomainContact, 'Only Contacts allowed');

        /** @var Provider $provider */
        $provider = $contact
            ->providers
            ->where('slug', ProviderSlug::REALTIME_REGISTER)
            ->where('type', ProviderType::DOMAIN)
            ->first();

        /** @var string $handle */
        $handle = Arr::get($fields, 'handle');

        $contact->providers()->updateExistingPivot(
            $provider,
            ['external_contact' => $handle],
            false,
        );

        return Action::message($this->translator->translate('nova-action.action_success'));
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make($this->translator->translate('nova-action.contact'), 'handle'),
        ];
    }
}
