<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Domain\Hosting\Plesk\Services\SecretKeyService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaGenerateSecretKeyAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SecretKeyService $secretKeyService,
    ) {
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.generate_secret_key');
    }

    /**
     * @param Collection<int, Server> $models
     */
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $server) {
            /** @var string[] $credentials */
            $credentials = $fields->toArray();

            /** @var Server $server */
            $secretKey = $this->secretKeyService->getPleskSecretKey(
                server: $server,
                credentials: $credentials
            );

            $server->update(['secret_key' => $secretKey]);
        }
    }

    /**
     * @return array<int, Text|Password>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make($this->translator->translate('server.attributes.username'), 'username'),
            Password::make($this->translator->translate('server.attributes.password'), 'password')
                ->fillUsing(function (NovaRequest $request, $model): void {
                    $model->password = $request->password;
                }),
        ];
    }
}
