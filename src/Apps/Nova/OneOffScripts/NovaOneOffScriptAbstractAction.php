<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts;

use Carbon\CarbonImmutable;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Http\Requests\NovaRequest;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Waterfront\Apps\OneOffScripts\OneOffScript;
use Waterfront\Infra\Authentication\AuthorizationChecker;
use Waterfront\Infra\Common\DateTimeFormat;

abstract class NovaOneOffScriptAbstractAction extends Action
{
    protected OneOffScript $oneOffScript;

    public function __construct()
    {
        /** @var AuthorizationChecker $authorizationChecker */
        $authorizationChecker = resolve(AuthorizationChecker::class);

        $this->canSee(fn (NovaRequest $request): bool => $authorizationChecker->can(Permissions::RUN_ONE_OFF_SCRIPT));
        $this->standalone();
        $this->onlyOnIndex();
        $this->confirmText('Are you sure you want to execute this one-off script?');
        $this->modalSize = '5xl';

        $this->oneOffScript = $this->findOrCreateBySlug(
            $this->getOneOffScriptSlug(),
            $this->getOneOffScriptTicketUrl()
        );
    }

    public function name(): string
    {
        return sprintf('One-off: %s', $this->getOneOffScriptSlug());
    }

    abstract protected function getOneOffScriptSlug(): string;

    abstract protected function getOneOffScriptTicketUrl(): string;

    /**
     * Helper function to get standard fields to show basic info about the action.
     *
     * @return array<Field>
     */
    protected function getOneOffScriptInfoFields(): array
    {
        return [
            URL::make('Ticket ref')
                ->default($this->oneOffScript->ticket_ref)
                ->readonly(),
            Date::make('Last executed at')
                ->default($this->oneOffScript->last_executed_at?->format(DateTimeFormat::DUTCH))
                ->readonly(),
        ];
    }

    protected function registerExecution(): void
    {
        $this->oneOffScript->last_executed_at = CarbonImmutable::now();
        $this->oneOffScript->save();
    }

    private function findOrCreateBySlug(string $slug, string $ticketRef): OneOffScript
    {
        /** @var OneOffScript $oneOff */
        $oneOff = OneOffScript::query()
            ->firstOrCreate([
                'slug' => $slug,
            ], [
                'slug' => $slug,
                'ticket_ref' => $ticketRef,
            ]);

        return $oneOff;
    }
}
