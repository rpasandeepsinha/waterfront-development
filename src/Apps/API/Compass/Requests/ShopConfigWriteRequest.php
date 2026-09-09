<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShopConfigWriteRequest extends FormRequest
{
    /** @return array<mixed> */
    public function rules(): array
    {
        return [
            'telco.changeContractPeriod' => ['nullable', 'integer:strict'],

            'telco.sections.domainPeriodSelection.show' => ['required', 'boolean'],
            'telco.sections.dns.show' => ['required', 'boolean'],
            'telco.sections.dns.blocks' => ['required', 'array', 'min:1'],
            'telco.sections.web.show' => ['required', 'boolean'],
            'telco.sections.web.blocks' => ['required', 'array', 'min:1'],
            'telco.sections.email.show' => ['required', 'boolean'],
            'telco.sections.email.blocks' => ['required', 'array', 'min:1'],
            'telco.sections.ssl.show' => ['required', 'boolean'],
            'telco.sections.ssl.blocks' => ['required', 'array', 'min:1'],

            'telco.sections.*.defaultAddProduct' => ['exists:products,slug'],
            'telco.sections.*.description.nl' => ['string'],
            'telco.sections.*.description.en' => ['string', 'required_with:telco.sections.*.description.nl'],

            'telco.sections.*.blocks.*.product' => ['exists:products,slug'],
            'telco.sections.*.blocks.*.modalProducts' => ['array'],
            'telco.sections.*.blocks.*.modalProducts.*' => ['exists:products,slug'],
            'telco.sections.*.blocks.*.subtitleProductName' => ['boolean'],
            'telco.sections.*.blocks.*.badge.nl' => ['string'],
            'telco.sections.*.blocks.*.badge.en' => ['string', 'required_with:telco.sections.*.blocks.*.badge.nl'],
            'telco.sections.*.blocks.*.title.nl' => ['string'],
            'telco.sections.*.blocks.*.title.en' => ['string', 'required_with:telco.sections.*.blocks.*.title.nl'],
            'telco.sections.*.blocks.*.description.nl' => ['string'],
            'telco.sections.*.blocks.*.description.en' => ['string', 'required_with:telco.sections.*.blocks.*.description.nl'],
            'telco.sections.*.blocks.*.usps.nl' => ['string'],
            'telco.sections.*.blocks.*.usps.en' => ['string', 'required_with:telco.sections.*.blocks.*.usps.nl'],
            'telco.sections.*.blocks.*.cons.nl' => ['string'],
            'telco.sections.*.blocks.*.cons.en' => ['string', 'required_with:telco.sections.*.blocks.*.cons.nl'],
        ];
    }
}
