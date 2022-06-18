<?php

namespace Jmsbrone\Graphql\attributes;

use Attribute;
use GraphQL\Type\Definition\Type;

/**
 * Decorator for resolver type methods to provide metadata about corresponding GraphQL operation type.
 */
#[Attribute]
class ResolverTypeMethod
{
    /**
     * @param string $name Graphql type name (name of the query or mutation)
     * @param Type|string $returnType Return type. Must be registered graphql type instance or its name
     * @param bool $nullable Whether method result can be null
     * @param bool $list Whether method result is a list instead of single value
     * @param string $description Description of the method
     */
    public function __construct(
        public string $name,
        public Type|string $returnType,
        public bool $nullable = true,
        public bool $list = false,
        public string $description = ''
    ) {
    }
}
