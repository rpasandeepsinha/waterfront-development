<?php

declare(strict_types=1);

namespace Waterfront\Domain\Translations\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int              $id
 * @property string           $locale
 * @property string           $display_name
 * @property bool             $active
 * @property bool             $default
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 *
 * @mixin Builder<TranslationLanguage>
 */
class TranslationLanguage extends Model
{
    protected $table = 'translation_languages';

    /**
     * @return HasMany<TranslationString, $this>
     */
    public function translationStrings(): HasMany
    {
        return $this->hasMany(TranslationString::class);
    }
}
