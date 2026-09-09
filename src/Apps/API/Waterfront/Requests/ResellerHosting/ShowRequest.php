<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\ResellerHosting;

use Illuminate\Foundation\Http\FormRequest;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;

/**
 * @property ResellerHostingDeployment $resellerHostingDeployment
 */
class ShowRequest extends FormRequest
{
    /**
     * @return array<string,array<string>>
     */
    public function rules(): array
    {
        return [];
    }

    protected function passesAuthorization(): bool
    {
        return ! $this->getValidatorInstance()->fails() && parent::passesAuthorization();
    }
}
