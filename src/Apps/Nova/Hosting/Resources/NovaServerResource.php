<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Resources;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean as NovaBoolField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Customers\Resources\NovaCustomerResource;
use Waterfront\Apps\Nova\Fields\Credential;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchPackageFromServer;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchPackagesFromServer;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchUserFromServer;
use Waterfront\Apps\Nova\Hosting\Actions\NovaFetchUserFromSitebuilderServer;
use Waterfront\Apps\Nova\Hosting\Actions\NovaGenerateSecretKeyAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaImportDirectAdminHostingServersAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaImportPleskHostingServersAction;
use Waterfront\Apps\Nova\Hosting\Actions\NovaVerifyServerHealthAction;
use Waterfront\Apps\Nova\Hosting\Filters\NovaServerAllowNewWebsitesFilter;
use Waterfront\Apps\Nova\Hosting\Filters\NovaServerOwnerFilter;
use Waterfront\Apps\Nova\Hosting\Filters\NovaServerTypeFilter;
use Waterfront\Domain\Hosting\Repositories\ServerRepository;
use Waterfront\Domain\Hosting\Rules\ValidServerRule;
use Waterfront\Domain\Servers\Enums\ServerType;
use Waterfront\Domain\Servers\Models\Server;

/** @property Server $resource */
class NovaServerResource extends Resource
{
    private const array CREDENTIAL_FIELDS = [
        'username',
        'password',
        'loginkey',
        'secret_key',
    ];

    public static string $model = Server::class;

    /** @var array<mixed> */
    public static $search = [
        'hostname',
        'name',
        'owner',
        'ipv4',
        'ipv6',
    ];

    public static function getTranslationKey(): string
    {
        return 'server';
    }

    public function title(): string
    {
        return $this->resource->hostname;
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.servers');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        $serverTypes = [
            ServerType::DIRECTADMIN->value => 'DIRECTADMIN',
            ServerType::DIRECTADMIN_MAIL->value => 'DIRECTADMIN_MAIL',
            ServerType::PLESK->value => 'PLESK',
            ServerType::SITEBUILDER->value => 'SITEBUILDER',
        ];

        return [
            ID::make()->hideFromIndex(),
            Select::make(self::translate('server.attributes.type'))
                ->options($serverTypes)
                ->displayUsingLabels()
                ->rules(['required', Rule::in(array_keys($serverTypes))])
                ->sortable(),
            Text::make(self::translate('server.attributes.owner'), 'owner')->nullable()->sortable(),
            Text::make(self::translate('server.attributes.name'), 'name')->sortable(),
            Text::make(self::translate('server.attributes.hostname'), 'hostname')
                ->rules('required')
                ->creationRules('unique:hosting_servers,hostname,NULL,id,deleted_at,NULL')
                ->updateRules('unique:hosting_servers,hostname,{{resourceId}},id,deleted_at,NULL')
                ->sortable(),
            Number::make(self::translate('server.attributes.port'), 'port')
                ->rules('required', 'integer', 'numeric', 'max:65535')
                ->sortable(),
            NovaBoolField::make(self::translate('server.attributes.use_ssl'), 'use_ssl')->hideFromIndex(),
            Text::make(self::translate('server.attributes.ipv4'), 'ipv4')->rules('nullable', 'ipv4')->sortable(),
            Text::make(self::translate('server.attributes.ipv6'), 'ipv6')->rules('nullable', 'ipv6')->sortable(),
            Text::make(self::translate('server.attributes.username'), 'username')
                ->rules('nullable', 'string')
                ->sortable(),
            Credential::make(self::translate('server.attributes.password'), 'password')
                ->onlyOnForms()
                ->creationRules('sometimes')
                ->updateRules('sometimes'),
            Credential::make(self::translate('server.attributes.login_key'), 'loginkey')
                ->onlyOnForms()
                ->creationRules('sometimes')
                ->updateRules('sometimes'),
            Credential::make(self::translate('server.attributes.secret_key'), 'secret_key')
                ->onlyOnForms()
                ->creationRules('sometimes')
                ->updateRules('sometimes'),
            NovaBoolField::make(
                self::translate('server.attributes.allow_new_websites'),
                'allow_new_websites',
            )->sortable(),
            Number::make(self::translate('server.attributes.maximum_websites'), 'maximum_websites')
                ->sortable()
                ->nullable(),
            BelongsTo::make(
                self::translate('server.relations.customer'),
                'customer',
                NovaCustomerResource::class,
            )
                ->nullable()
                ->searchable()
                ->sortable(),
            NovaBoolField::make(
                self::translate('server.attributes.customer_login_as_admin'),
                'customer_login_as_admin',
            )->hideFromIndex(),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            resolve(NovaServerTypeFilter::class),
            resolve(NovaServerOwnerFilter::class),
            resolve(NovaServerAllowNewWebsitesFilter::class),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            resolve(NovaGenerateSecretKeyAction::class),
            resolve(NovaFetchUserFromServer::class),
            resolve(NovaFetchUserFromSitebuilderServer::class),
            resolve(NovaFetchPackagesFromServer::class),
            resolve(NovaFetchPackageFromServer::class),
            resolve(NovaImportDirectAdminHostingServersAction::class)->onlyOnIndex()->standalone(),
            resolve(NovaImportPleskHostingServersAction::class)->onlyOnIndex()->standalone(),
            resolve(NovaVerifyServerHealthAction::class)->onlyOnIndex()->standalone(),
        ];
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public static function authorizable(): bool
    {
        return false;
    }

    protected static function afterValidation(NovaRequest $request, Validator $validator): void
    {
        $serverData = self::validationPayload($request);
        $serverData['domain'] = $serverData['hostname'] ?? null;

        /** @var ValidServerRule $validServerRule */
        $validServerRule = Container::getInstance()->make(ValidServerRule::class);
        $validServerRule->setData($serverData);
        $validServerRule->validate(
            '',
            null,
            function (string $message) use ($validator) {
                $message = self::translate($message);
                $validator->errors()->add('type', $message);

                return $message;
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validationPayload(NovaRequest $request): array
    {
        $incomingData = $request->only(new Server()->getFillable());
        if (! $request->isUpdateOrUpdateAttachedRequest()) {
            return $incomingData;
        }

        /** @var ServerRepository $serverRepository */
        $serverRepository = Container::getInstance()->make(ServerRepository::class);

        try {
            $server = $serverRepository->getById((int) $request->resourceId);
        } catch (ModelNotFoundException) {
            return $incomingData;
        }

        return self::mergeForValidation($server, $incomingData);
    }

    /**
     * @param array<string, mixed> $incomingData
     *
     * @return array<string, mixed>
     */
    private static function mergeForValidation(Server $server, array $incomingData): array
    {
        $merged = array_replace($server->attributesToArray(), $incomingData);

        foreach (self::CREDENTIAL_FIELDS as $field) {
            if (
                ! array_key_exists($field, $incomingData)
                || $incomingData[$field] === null
                || $incomingData[$field] === ''
            ) {
                $merged[$field] = $server->getAttribute($field);
            }
        }

        return $merged;
    }
}
