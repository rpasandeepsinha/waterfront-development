<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Redirects\Services;

use DOMDocument;
use Waterfront\Domain\Provision\Redirects\DTO\Redirect;
use Waterfront\Domain\Provision\Redirects\DTO\RedirectSourceMatchers;
use Waterfront\Domain\Provision\Redirects\Enums\RedirectType;
use Waterfront\Domain\Provision\Redirects\Exceptions\CaddyMapperException;
use Waterfront\Infra\CaddyClient\DTO\RedirectRoute;
use Waterfront\Infra\CaddyClient\Enums\RedirectType as CaddyRedirectType;

class CaddyProvisionClientMapper
{
    private const string MAPPER_EXCEPTION_MESSAGE = '%s could not be determined from Caddy DTO';

    public function parseSourceMatchers(string $fromUrl): RedirectSourceMatchers
    {
        $urlForParsing = (bool) preg_match('#^https?://#i', $fromUrl) ? $fromUrl : 'https://' . $fromUrl;

        $host = parse_url($urlForParsing, PHP_URL_HOST);
        $path = parse_url($urlForParsing, PHP_URL_PATH);
        $rawQuery = parse_url($urlForParsing, PHP_URL_QUERY);

        return new RedirectSourceMatchers(
            host: is_string($host) && $host !== '' ? $host : $fromUrl,
            paths: is_string($path) && $path !== '/' ? [$path] : null,
            query: is_string($rawQuery) && $rawQuery !== '' ? $this->normalizeQueryString($rawQuery) : null,
        );
    }

    public function getCaddyRedirectType(RedirectType $redirectType): CaddyRedirectType
    {
        return match ($redirectType) {
            RedirectType::PERMANENT => CaddyRedirectType::MOVED_PERMANENTLY,
            RedirectType::TEMPORARY => CaddyRedirectType::FOUND,
            RedirectType::FRAME => CaddyRedirectType::FRAME,
        };
    }

    /**
     * @throws CaddyMapperException
     */
    public function getRedirectDtoFromCaddyDto(RedirectRoute $redirectRoute): Redirect
    {
        return new Redirect(
            source: $this->getSourceFromCaddyDto($redirectRoute),
            destination: $this->getDestinationFromCaddyDto($redirectRoute),
            redirectType: $this->getRedirectTypeFromCaddyDto($redirectRoute),
        );
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function normalizeQueryString(string $queryString): ?array
    {
        $query = [];

        foreach (explode('&', $queryString) as $parameter) {
            if ($parameter === '') {
                continue;
            }

            [$rawName, $rawValue] = str_contains($parameter, '=') ? explode('=', $parameter, 2) : [$parameter, ''];

            $name = $this->normalizeQueryParameterName(urldecode($rawName));

            if ($name === '') {
                continue;
            }

            $query[$name][] = urldecode($rawValue);
        }

        return $query === [] ? null : $query;
    }

    private function normalizeQueryParameterName(string $name): string
    {
        return preg_replace('/\[(?:\d*)]$/', '', $name) ?? $name;
    }

    /**
     * @throws CaddyMapperException
     */
    private function getSourceFromCaddyDto(RedirectRoute $redirectRoute): string
    {
        $match = array_first($redirectRoute->match);
        if ($match === null || $match->host === null) {
            throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'source'));
        }

        $host = array_first($match->host);
        if ($host === null) {
            throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'source.host'));
        }

        $path = $match->path !== null ? array_first($match->path) ?? '' : '';
        $query = $this->buildQueryString($match->query);

        return $host . $path . $query;
    }

    /**
     * @param array<string, list<string>>|null $query
     */
    private function buildQueryString(?array $query): string
    {
        if ($query === null || $query === []) {
            return '';
        }

        $parts = [];

        foreach ($query as $key => $values) {
            foreach ($values as $value) {
                $parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }

        return $parts === [] ? '' : '?' . implode('&', $parts);
    }

    /**
     * @throws CaddyMapperException
     */
    private function getDestinationFromCaddyDto(RedirectRoute $redirectRoute): string
    {
        $handle = array_first($redirectRoute->handle);
        if ($handle === null) {
            throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'Handle'));
        }

        switch ($this->getRedirectTypeFromCaddyDto($redirectRoute)) {
            case RedirectType::PERMANENT:
            case RedirectType::TEMPORARY:
                if ($handle->location === null) {
                    throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'Destination'));
                }

                return $handle->location;
            case RedirectType::FRAME:
                if ($handle->body === null) {
                    throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'Destination'));
                }

                $dom = new DOMDocument();
                $dom->loadHTML($handle->body);
                $iframes = $dom->getElementsByTagName('iframe');
                if ($iframes->length === 0) {
                    throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'Destination'));
                }

                return $iframes->item(0)?->getAttribute('src') ?? '';
        }
    }

    /**
     * @throws CaddyMapperException
     */
    private function getRedirectTypeFromCaddyDto(RedirectRoute $redirectRoute): RedirectType
    {
        $handle = array_first($redirectRoute->handle);
        if ($handle === null) {
            throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'handle'));
        }

        if ($handle->statusCode === 200 && $handle->body !== null && str_contains($handle->body, '</iframe>')) {
            return RedirectType::FRAME;
        }

        return match ($handle->statusCode) {
            301 => RedirectType::PERMANENT,
            302 => RedirectType::TEMPORARY,
            default => throw new CaddyMapperException(sprintf(self::MAPPER_EXCEPTION_MESSAGE, 'RedirectType')),
        };
    }
}
