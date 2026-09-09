<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int                                $id
 * @property string                             $key
 * @property string                             $source
 * @property ?CarbonImmutable                   $created_at
 * @property ?CarbonImmutable                   $updated_at
 * @property Collection<int, TranslationString> $translationStrings
 *
 * @mixin Builder<TranslationKey>
 */
class TranslationKey extends Model
{
    use HasTimestamps;

    protected $table = 'translation_keys';

    /** @return HasMany<TranslationString, $this> */
    public function translationStrings(): HasMany
    {
        return $this->hasMany(TranslationString::class, 'key_id');
    }

    /**
     * @return Collection<string, string|null>
     */
    public function getTranslations(): Collection
    {
        return $this->translationStrings->mapWithKeys(fn (TranslationString $translationString) => [$translationString->language->locale => $translationString->translated_string]);
    }
}
