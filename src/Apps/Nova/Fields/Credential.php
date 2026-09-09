<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Fields;

use Laravel\Nova\Fields\Password;
use Override;

class Credential extends Password
{
    #[Override]
    public function fillModelWithData(object $model, mixed $value, string $attribute): void
    {
        if ($value !== null && $value !== '') {
            $attributes = [str_replace('.', '->', $attribute) => $value];

            $model->forceFill($attributes);
        }
    }
}
