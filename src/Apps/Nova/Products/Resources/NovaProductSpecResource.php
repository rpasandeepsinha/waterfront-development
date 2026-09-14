<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Resources;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\General\Resources\Resource;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\Rules\ProductSpecValue;
use Waterfront\Infra\Translation\TranslatorInterface;

/** @property ProductSpec $resource */
class NovaProductSpecResource extends Resource
{
    public static string $model = ProductSpec::class;

    /**
     * @var array<mixed>
     */
    public static $search = [
        'name',
        'value',
    ];

    public static $globallySearchable = false;

    public static function getTranslationKey(): string
    {
        return 'product-spec';
    }

    public function title(): string
    {
        return self::translate(sprintf('product-spec.%s', $this->resource->name));
    }

    public static function label(): string
    {
        return self::translate('nova-resource-labels.specifications');
    }

    /**
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        $product = $request->product;
        assert(is_null($product) || is_int($product) || is_string($product));

        return [
            BelongsTo::make(
                self::translate('product-spec.relations.product'),
                'product',
                NovaProductResource::class,
            ),
            Select::make(self::translate('product-spec.attributes.name'), 'name')
                ->options(self::getSpecsAsSelect())
                ->searchable()
                ->displayUsingLabels()
                ->rules(
                    'required',
                    Rule::unique('product_specs')->ignore($request->route('resourceId'))->where('product_id', $product),
                ),
            Text::make(self::translate('product-spec.attributes.value'), 'value')
                ->rules(['required', new ProductSpecValue($request)])
                ->dependsOn(['name'], function (Text $field, NovaRequest $request, FormData $formData) {
                    $name = $formData->get('name');
                    if ($name === null) {
                        return;
                    }

                    assert(is_string($name));
                    $explanation = self::translate(sprintf('product-spec.%s.explanation', $name));
                    if (str_starts_with($explanation, 'product-spec.')) {
                        $explanation = '';
                    } else {
                        $explanation = str_replace("\n", '<br />', $explanation);
                    }

                    $field->help($explanation);
                }),
        ];
    }

    /**
     * Get specs as options for a select list. Filters out specs that should not be displayed in a form.
     *
     * @return array{label: string, group?: string}
     */
    private static function getSpecsAsSelect(): array
    {
        $specs = Config::get('product-specs');
        assert(is_array($specs));

        $translator = resolve(TranslatorInterface::class);

        $names = self::buildNameListRecursive($specs, '', true);

        $labels = array_map(fn ($name) => $translator->translate('product-spec.' . $name), $names);

        /** @var array{label: string, group?: string} $options */
        $options = array_combine($names, $labels);
        asort($options);

        return $options;
    }

    /**
     * @param array<mixed> $data
     * @param bool         $onlyInForm Set this to true to filter out items that should not be displayed in a form
     *
     * @return array<int, string>
     */
    private static function buildNameListRecursive(array $data, string $dotKey = '', bool $onlyInForm = false): array
    {
        $list = [];
        foreach ($data as $key => $value) {
            $compoundKey = $dotKey;
            if ($compoundKey !== '') {
                $compoundKey .= '.';
            }

            $compoundKey .= $key;

            if (is_array($value) && array_key_exists('type', $value)) {
                if (
                    ! $onlyInForm
                    || array_key_exists('show-in-form', $value)
                    && $value['show-in-form'] !== ''
                    && $value['show-in-form'] !== null
                ) {
                    $list[] = $compoundKey;
                }
            } else {
                assert(is_array($value));
                $list = [...$list, ...self::buildNameListRecursive($value, $compoundKey, $onlyInForm)];
            }
        }

        return $list;
    }
}
