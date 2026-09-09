<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Resources;

use Illuminate\Http\Request;
use ReflectionMethod;
use Waterfront\Apps\API\Compass\Attributes\ExcludeField;

trait RespectsExcludedFields
{
    /** @var string[]|null */
    private ?array $excludedFieldsCache = null;

    /** @return string[] */
    protected function excludedFields(Request $request): array
    {
        if ($this->excludedFieldsCache !== null) {
            return $this->excludedFieldsCache;
        }

        $uses = $request->route()?->getAction('uses');

        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return $this->excludedFieldsCache = [];
        }

        [$controller, $method] = explode('@', $uses, 2);

        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return $this->excludedFieldsCache = [];
        }

        $attributes = new ReflectionMethod($controller, $method)->getAttributes(ExcludeField::class);

        if ($attributes === []) {
            return $this->excludedFieldsCache = [];
        }

        return $this->excludedFieldsCache = $attributes[0]->newInstance()->fields;
    }

    protected function fieldExcluded(Request $request, string $field): bool
    {
        return in_array($field, $this->excludedFields($request), true);
    }
}
