<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Translations\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Translations\Models\TranslationString;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class TranslationStringsUnique extends AbstractValidator implements DataAwareRule
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
        if ($this->data['editMode'] === 'create') {
            $tableName = new TranslationString()->getTable();
            $query = DB::table($tableName)
                ->where('key_id', $this->data['translationkey'])
                ->where('language_id', $this->data['language'])
                ->value('translated_string');

            return $query === '';
        }

        return true;
    }

    protected function message(): string
    {
        return resolve(TranslatorInterface::class)->translate('nova-action.error.translation_key_already_exists');
    }
}
