<?php

declare(strict_types=1);

namespace Waterfront\Support\Helpers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

class RouteAttributes
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @return list<T>
     */
    public function getFromRequest(Request $request, string $className): array
    {
        $currentRoute = $request->route();

        if (! $currentRoute instanceof Route) {
            return [];
        }

        $controller = $currentRoute->getControllerClass();

        // Controller can be a closure
        if ($controller === null) {
            return [];
        }

        /** @var class-string $controller */
        $action = $currentRoute->getActionMethod();

        return $this->getFromControllerAction($controller, $action, $className);
    }

    /**
     * @template T of object
     *
     * @param class-string    $controller
     * @param class-string<T> $attributeClassName
     *
     * @throws ReflectionException
     *
     * @return list<T>
     */
    private function getFromControllerAction(string $controller, string $action, string $attributeClassName): array
    {
        /** @var class-string $controller */
        $reflectionClass = new ReflectionClass($controller);
        $reflectionMethod = $reflectionClass->getMethod($action);

        $attributes = $reflectionMethod->getAttributes($attributeClassName, ReflectionAttribute::IS_INSTANCEOF);

        return array_map(static fn (ReflectionAttribute $attribute) => $attribute->newInstance(), $attributes);
    }
}
