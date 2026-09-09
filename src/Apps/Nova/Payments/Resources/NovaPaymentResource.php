<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Payments\Resources;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Payments\Models\Payment;

/** @property Payment $resource */
class NovaPaymentResource extends Resource
{
    public static string $model = Payment::class;

    public static $displayInNavigation = false;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'id',
    ];

    public static function getTranslationKey(): string
    {
        return 'payment';
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.payments');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(self::translate('payment.attributes.id'), 'id'),
            Text::make(self::translate('payment.attributes.external_id'), 'external_id'),
            Currency::make(self::translate('payment.attributes.amount'), 'amount')
                ->currency('EUR')
                ->step('0.01')
                ->asMinorUnits(),
            Text::make(self::translate('payment.attributes.status'), 'status', fn (): string => $this->resource->status->value),
            Boolean::make(self::translate('payment.attributes.create-direct-debit-mandate'), 'create_direct_debit_mandate'),
        ];
    }

    public function authorizedToView(Request $request): bool
    {
        return true;
    }

    public function authorizedToUpdate(Request $request): bool
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

    public function authorizedToDeleteForSerialization(Request $request): bool
    {
        return false;
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }
}
