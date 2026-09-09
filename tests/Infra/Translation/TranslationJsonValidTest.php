<?php

declare(strict_types=1);

namespace Tests\Infra\Translation;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversNothing]
class TranslationJsonValidTest extends TestCase
{
    #[Test]
    public function jsonIsValid(): void
    {
        $files = Storage::disk('translations')->files();
        $jsonFilenames = array_filter($files, fn ($file) => pathinfo($file, PATHINFO_EXTENSION) === 'json');

        foreach ($jsonFilenames as $jsonFilename) {
            $translationJson = Storage::disk('translations')->get($jsonFilename);

            self::assertTrue(
                Str::isJson($translationJson),
                "Translation file '$jsonFilename' contains invalid JSON!"
            );

            $duplicateKeys = $this->findDuplicateJsonKeys($translationJson);

            self::assertCount(
                0,
                $duplicateKeys,
                "Translation file '$jsonFilename' contains duplicate keys: " . implode(', ', $duplicateKeys)
            );
        }
    }

    /**
     * @return array<string>
     */
    private function findDuplicateJsonKeys(string $json): array
    {
        $cleanJson = str_replace(["\r\n", "\r", "\n", ' '], '', $json);

        preg_match_all('/"(?<key>[^"]+)":{"locales"/', $cleanJson, $matches);

        return array_diff_assoc(
            $matches['key'],
            array_unique($matches['key'])
        );
    }
}
