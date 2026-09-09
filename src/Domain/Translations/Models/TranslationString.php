<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property TranslationLanguage $language
 * @property int                 $language_id
 * @property TranslationKey      $translationKey
 * @property int                 $key_id
 * @property ?string             $translated_string
 * @property ?CarbonImmutable    $created_at
 * @property ?CarbonImmutable    $updated_at
 *
 * @mixin Builder<TranslationString>
 */
class TranslationString extends Model
{
    protected $table = 'translation_strings';

    /** @return BelongsTo<TranslationKey, $this> */
    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'key_id', 'id', 'translationkey');
    }

    /** @return BelongsTo<TranslationLanguage, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(TranslationLanguage::class, 'language_id', 'id', 'languages');
    }

    /** @return array<string> */
    public static function getGroup(string $group, string $locale): array
    {
        /** @var string[] $translations */
        $translations = TranslationString::query()
            ->with(['translationKey', 'language'])
            ->whereHas('language', function (Builder $query) use ($locale): void {
                $query->where('locale', $locale);
            })
            ->where(function (Builder $query) use ($group): void {
                /** Ignore the group when an asterisk is given */
                if ($group === '*') {
                    return;
                }

                $query->whereHas('translationKey', function (Builder $query) use ($group): void {
                    $query->where('source', $group);
                });
            })
            ->whereNotNull('translated_string')
            ->get()
            ->mapWithKeys(fn (TranslationString $translationString): array => [
                $translationString->translationKey->key => $translationString->translated_string,
            ])
            ->toArray();

        return $translations;
    }
}
