<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Rules;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Validation\DataAwareRule;
use Waterfront\Domain\Translations\Models\TranslationLanguage;
use Waterfront\Domain\Translations\Models\TranslationString;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;
use Webmozart\Assert\Assert;

class TranslationKeyHasAllLanguages extends AbstractValidator implements DataAwareRule
{
    /**
     * @var array<mixed>
     */
    protected $data = [];

    /**
     * @param array<mixed> $data
     */
    public function setData($data): static
    {
        $this->data = $data;

        return $this;
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        $languages = TranslationLanguage::query()->pluck('id');

        Assert::string($value, 'Given value is not a string.');

        $translationStrings = TranslationString::query()->whereHas(
            'translationKey',
            fn (Builder $builder) => $builder->where('key', $value),
        )->get();

        if ($translationStrings->count() < 1) {
            return false;
        }

        foreach ($languages as $language) {
            if (! $translationStrings->contains(
                fn (TranslationString $string) => (
                    $string->language_id === $language
                    && $string->translated_string !== null
                ),
            )) {
                return false;
            }
        }

        return true;
    }

    protected function message(): string
    {
        return resolve(TranslatorInterface::class)->translate('validation.translation_key.does_not_exist');
    }
}
