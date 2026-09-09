<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Database\Seeders\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Waterfront\Domain\Translations\Models\TranslationLanguage;

class TranslationLanguageSeeder extends Seeder
{
    public function run(): void
    {
        $this->insertLanguages();
    }

    private function insertLanguages(): void
    {
        DB::table(new TranslationLanguage()->getTable())->insert([
            'display_name' => 'Nederlands',
            'locale' => 'nl',
            'active' => true,
            'default' => true,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        DB::table(new TranslationLanguage()->getTable())->insert([
            'display_name' => 'English',
            'locale' => 'en',
            'active' => true,
            'default' => false,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }
}
