<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\ActionEvents;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ramsey\Uuid\UuidInterface;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\Helpers\PermissionsHelper;

class IdentityAwareActionEvent extends ActionEvent
{
    public static function defaultAttributes(
        ActionRequest $request,
        Action $action,
        string $batchId,
        string $status = 'running',
    ): array {
        return self::replaceUserAttributesWithIdentity(parent::defaultAttributes($request, $action, $batchId, $status));
    }

    public static function forResourceUpdate($user, $model): static
    {
        $actionEvent = parent::forResourceUpdate($user, $model);

        self::replaceUserAttributesInActionEventWithIdentity($actionEvent);

        return $actionEvent;
    }

    public static function forResourceCreate($user, $model): static
    {
        $actionEvent = parent::forResourceCreate($user, $model);

        self::replaceUserAttributesInActionEventWithIdentity($actionEvent);

        return $actionEvent;
    }

    public static function forAttachedResource(NovaRequest $request, $parent, $pivot): static
    {
        $actionEvent = parent::forAttachedResource($request, $parent, $pivot);

        self::replaceUserAttributesInActionEventWithIdentity($actionEvent);

        return $actionEvent;
    }

    public static function forAttachedResourceUpdate(NovaRequest $request, $parent, $pivot): static
    {
        $actionEvent = parent::forAttachedResourceUpdate($request, $parent, $pivot);

        self::replaceUserAttributesInActionEventWithIdentity($actionEvent);

        return $actionEvent;
    }

    /**
     * @param Authenticatable        $user
     * @param Collection<int, Model> $models
     *
     * @return Collection<int, ActionEvent>
     */
    public static function forResourceDelete($user, Collection $models): Collection
    {
        $actionEventCollection = parent::forResourceDelete($user, $models);

        self::replaceUserAttributesInActionEventCollectionWithIdentity($actionEventCollection);

        return $actionEventCollection;
    }

    /**
     * @param Authenticatable        $user
     * @param Collection<int, Model> $models
     *
     * @return Collection<int, ActionEvent>
     */
    public static function forResourceRestore($user, Collection $models): Collection
    {
        $actionEventCollection = parent::forResourceRestore($user, $models);

        self::replaceUserAttributesInActionEventCollectionWithIdentity($actionEventCollection);

        return $actionEventCollection;
    }

    /**
     * @param Authenticatable        $user
     * @param Collection<int, Model> $models
     *
     * @return Collection<int, ActionEvent>
     */
    public static function forSoftDeleteAction(string $action, $user, Collection $models): Collection
    {
        $actionEventCollection = parent::forSoftDeleteAction($action, $user, $models);

        self::replaceUserAttributesInActionEventCollectionWithIdentity($actionEventCollection);

        return $actionEventCollection;
    }

    /**
     * @param Authenticatable        $user
     * @param Model                  $parent
     * @param Collection<int, Model> $models
     *
     * @return Collection<int, ActionEvent>
     */
    public static function forResourceDetach($user, $parent, Collection $models, string $pivotClass): Collection
    {
        $actionEvent = parent::forResourceDetach($user, $parent, $models, $pivotClass);

        self::replaceUserAttributesInActionEventCollectionWithIdentity($actionEvent);

        return $actionEvent;
    }

    /**
     * @param mixed[] $source
     *
     * @return mixed[]
     */
    private static function replaceUserAttributesWithIdentity(array $source): array
    {
        if (array_key_exists('user_id', $source)) {
            unset($source['user_id']);
        }

        return array_merge(self::getIdentityInformation(), $source);
    }

    private static function replaceUserAttributesInActionEventWithIdentity(ActionEvent $actionEvent): void
    {
        $identityInformation = self::getIdentityInformation();

        unset($actionEvent['user_id']);
        $actionEvent['identity_uuid'] = $identityInformation['identity_uuid'];
        $actionEvent['identity_metadata'] = $identityInformation['identity_metadata'];
    }

    /**
     * @param Collection<int, ActionEvent> $actionEvents
     */
    private static function replaceUserAttributesInActionEventCollectionWithIdentity(Collection $actionEvents): void
    {
        $actionEvents->map(function ($actionEvent) {
            self::replaceUserAttributesInActionEventWithIdentity($actionEvent);
        });
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws JsonException
     *
     * @return array<UuidInterface|string>
     *
     */
    private static function getIdentityInformation(): array
    {
        /** @var AuthenticationManager $authenticationManager */
        $authenticationManager = resolve(AuthenticationManager::class);

        $authenticatedEmployee = $authenticationManager->getAuthenticatedEmployee();

        return [
            'identity_uuid' => $authenticatedEmployee->identitySchema->id,
            'identity_metadata' => json_encode([
                'email' => $authenticatedEmployee->identitySchema->traits?->email,
                'schemaId' => PermissionsHelper::getKratosSchemaId($authenticatedEmployee),
            ], JSON_THROW_ON_ERROR),
        ];
    }
}
