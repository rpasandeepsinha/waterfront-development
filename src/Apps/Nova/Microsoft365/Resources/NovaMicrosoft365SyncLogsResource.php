<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Microsoft365\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Microsoft365\Models\Microsoft365SyncLog;
use Waterfront\Infra\Common\DateTimeFormat;

/** @property Microsoft365SyncLog $resource */
class NovaMicrosoft365SyncLogsResource extends Resource
{
    public static string $model = Microsoft365SyncLog::class;

    public static $globallySearchable = false;

    /** @var array<mixed> */
    public static $with = ['microsoft365CustomerInfo', 'microsoft365Deployment'];

    /** @var array<mixed> */
    public static $search = [
        'log',
    ];

    public static function getTranslationKey(): string
    {
        return 'microsoft365-sync-logs';
    }

    public static function group(): string
    {
        return self::translate('nova-group.microsoft365');
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.microsoft365-sync-logs');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(
                self::translate('microsoft365-logs.log'),
                'log'
            )->displayUsing(function ($log) use ($request) {
                assert(is_string($log));
                if ($this->isResourceIndexRequest($request)) {
                    return Str::limit($log, 150);
                }
                return $log;
            }),
            BelongsTo::make(
                self::translate('customer.singular'),
                'microsoft365CustomerInfo',
                NovaMicrosoft365CustomerResource::class
            ),
            BelongsTo::make(
                self::translate('microsoft365-logs.subscription'),
                'microsoft365Deployment',
                NovaMicrosoft365DeploymentResource::class
            ),
            DateTime::make(
                self::translate('microsoft365-logs.time'),
                'created_at'
            )
            ->displayUsing(fn () => $this->resource->created_at?->format(DateTimeFormat::DUTCH))
            ->sortable(),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToForceDelete(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }
}
