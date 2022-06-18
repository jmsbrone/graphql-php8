<?php

namespace Jmsbrone\Graphql\exceptions;

use GraphQL\Error\Error;

/**
 * Exception is thrown in case when trying to load unknown graphql type.
 */
class TypeNotFoundException extends Error
{
    /**
     * @param string $typeName Type name that was attempted to be loaded
     */
    public function __construct(string $typeName)
    {
        parent::__construct("Type '$typeName' is not found!");
    }
}
