<?php

namespace Jmsbrone\Graphql;

use Attribute;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Helper for extracting data from reflections.
 * Provides methods for getting attributes from different objects.
 */
class ReflectionHelper
{
    /**
     * Returns first found method attribute.
     *
     * @param ReflectionMethod $method Method to check
     * @param string $class Attribute class to find in method attributes
     * @return Attribute|null Instance of first found attribute of requested class or null
     */
    public static function getMethodAttribute(ReflectionMethod $method, string $class): ?Attribute
    {
        $attributes = $method->getAttributes($class);

        return self::getFirstAttributeIfExists($attributes);
    }

    /**
     * Returns first found argument attribute.
     *
     * @param ReflectionParameter $argument Method argument to check
     * @param string $class Attribute class to find in argument attributes
     * @return Attribute|null Instance of first found attribute of requested class or null
     */
    public static function getArgumentAttribute(ReflectionParameter $argument, string $class): ?Attribute
    {
        $attributes = $argument->getAttributes($class);

        return self::getFirstAttributeIfExists($attributes);
    }

    /**
     * Returns first found property attribute.
     *
     * @param ReflectionProperty $property Class property to check
     * @param string $class Attribute class to find in class property
     * @return Attribute|null Attribute instance if found or null
     */
    public static function getPropertyAttribute(ReflectionProperty $property, string $class): ?Attribute
    {
        $attributes = $property->getAttributes($class);

        return self::getFirstAttributeIfExists($attributes);
    }

    /**
     * Returns attribute instance for first attribute in the given list.
     *
     * @param ReflectionAttribute[] $attributes List of attributes
     * @return Attribute|null Attribute instance if found or null
     */
    protected static function getFirstAttributeIfExists(array $attributes): ?Attribute
    {
        if (count($attributes) > 0) {
            $attribute = $attributes[0]->newInstance();
        } else {
            $attribute = null;
        }

        return $attribute;
    }
}
