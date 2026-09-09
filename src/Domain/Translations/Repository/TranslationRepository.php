<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Repository;

use Waterfront\Domain\Translations\Models\TranslationKey;

class TranslationRepository
{
    public function getTranslationByKey(string $key): TranslationKey
    {
        return TranslationKey::where('key', $key)->firstOrFail();
    }
}
