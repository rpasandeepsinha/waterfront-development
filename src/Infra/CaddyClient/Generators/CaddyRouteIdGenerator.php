<?php

declare(strict_types=1);

namespace Waterfront\Infra\CaddyClient\Generators;

readonly class CaddyRouteIdGenerator
{
    private const string ROUTE_ID_PREFIX = 'redirect:';
    private const string ROOT_SEGMENT = 'root';
    private const string PATH_SEPARATOR = '--';
    private const string QUERY_SEGMENT_SEPARATOR = '--';
    private const string QUERY_VALUE_SEPARATOR = '-or-';
    private const int HASH_LENGTH = 8;

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    public function generate(string $fromHost, ?array $paths = null, ?array $query = null): string
    {
        return sprintf(
            '%s%s:%s:%s:%s',
            self::ROUTE_ID_PREFIX,
            $this->normalizeHost($fromHost),
            $this->normalizePaths($paths),
            $this->normalizeQuery($query),
            $this->buildHashSuffix($fromHost, $paths, $query),
        );
    }

    private function normalizeHost(string $host): string
    {
        return $this->normalizeSegment($host, '/[^a-z0-9.-]+/', trimDashes: false);
    }

    /**
     * @param list<string>|null $paths
     */
    private function normalizePaths(?array $paths): string
    {
        if ($paths === null || $paths === []) {
            return self::ROOT_SEGMENT;
        }

        return implode(
            self::PATH_SEPARATOR,
            array_map($this->normalizePath(...), $paths),
        );
    }

    private function normalizePath(string $path): string
    {
        $normalized = strtolower(trim($path));

        if ($normalized === '' || $normalized === '/') {
            return self::ROOT_SEGMENT;
        }

        $normalized = preg_replace('#^/+#', '', $normalized) ?? $normalized;
        $normalized = str_replace(['/*', '*'], ['/wildcard', '-wildcard'], $normalized);

        return $this->normalizeSegment($normalized, '/[^a-z0-9]+/');
    }

    /**
     * @param array<string, list<string>>|null $query
     */
    private function normalizeQuery(?array $query): string
    {
        if ($query === null || $query === []) {
            return self::ROOT_SEGMENT;
        }

        ksort($query);

        $segments = [];

        foreach ($query as $key => $values) {
            $normalizedValues = array_map($this->normalizeQueryToken(...), $values);
            sort($normalizedValues);

            $segments[] = sprintf(
                '%s-%s',
                $this->normalizeQueryToken($key),
                implode(self::QUERY_VALUE_SEPARATOR, $normalizedValues),
            );
        }

        return implode(self::QUERY_SEGMENT_SEPARATOR, $segments);
    }

    private function normalizeQueryToken(string $value): string
    {
        return $this->normalizeSegment($value, '/[^a-z0-9]+/');
    }

    private function normalizeSegment(
        string $value,
        string $invalidCharactersPattern,
        bool $trimDashes = true,
    ): string {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace($invalidCharactersPattern, '-', $normalized) ?? $normalized;

        if ($trimDashes) {
            $normalized = trim($normalized, '-');
        }

        return $normalized === '' ? self::ROOT_SEGMENT : $normalized;
    }

    /**
     * @param list<string>|null                $paths
     * @param array<string, list<string>>|null $query
     */
    private function buildHashSuffix(string $fromHost, ?array $paths, ?array $query): string
    {
        $canonicalQuery = $this->canonicalizeQueryForHash($query);

        $canonical = json_encode(
            [
                'host' => strtolower(trim($fromHost)),
                'paths' => $paths ?? [],
                'query' => $canonicalQuery,
            ],
            JSON_THROW_ON_ERROR,
        );

        return substr(sha1($canonical), 0, self::HASH_LENGTH);
    }

    /**
     * @param array<string, list<string>>|null $query
     *
     * @return array<string, list<string>>
     */
    private function canonicalizeQueryForHash(?array $query): array
    {
        if ($query === null || $query === []) {
            return [];
        }

        ksort($query);

        foreach ($query as $key => $values) {
            sort($values);
            $query[$key] = $values;
        }

        return $query;
    }
}
