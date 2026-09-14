<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Rules\ProductBelongsToGroup;
use Waterfront\Infra\Common\DateTimeFormat;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 *
 * @property string  $product_uuid
 * @property int     $amount
 * @property int     $discount_percentage
 * @property string  $execution_date
 * @property string  $status
 * @property ?string $comment
 * @property ?bool   $invoice_now
 */
class CreateOneTimeServiceRequest extends FormRequest
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_uuid' => [
                'required',
                'uuid',
                new ProductBelongsToGroup(
                    $this->translator,
                    $this->productRepository,
                    ProductGroupType::ONE_TIME_SERVICE,
                ),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'discount_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'execution_date' => ['required', 'date_format:' . DateTimeFormat::DATE, 'after_or_equal:today'],
            'status' => ['required', Rule::enum(OneTimeServiceStatus::class)],
            'comment' => ['sometimes', 'nullable', 'string'],
            'invoice_now' => ['sometimes', 'boolean'],
        ];
    }
}
