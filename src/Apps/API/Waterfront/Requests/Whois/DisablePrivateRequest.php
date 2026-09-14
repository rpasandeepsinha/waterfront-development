<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Whois;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Rules\ProductSpecRule;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property string $domain
 */
class DisablePrivateRequest extends FormRequest
{
    /**
     * @throws BindingResolutionException
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        $translator = $this->container->make(TranslatorInterface::class);

        return [
            'domain' => [
                'required',
                new ProductSpecRule(
                    $translator,
                    ProductGroupType::EXTENSION,
                    'domain.allow_whois_private',
                    ['yes'],
                ),
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    public function all($keys = null): array
    {
        // Add the route parameter to the request so we can use it for validation
        $data = parent::all($keys);
        $data['domain'] = $this->route('domain');

        return $data;
    }
}
