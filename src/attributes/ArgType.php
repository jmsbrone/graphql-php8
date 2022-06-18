<?php

namespace Jmsbrone\Graphql\attributes;

use Attribute;

/**
 * Attribute for explicitly setting argument graphql type information.
 */
#[Attribute]
class ArgType
{
    /**
     * @param string $graphqlType One of registered GraphQL types. If omitted type must be inferred from php type
     * this attribute is set on
     * @param bool $list If true argument is considered to be a list (should be set with array types)
     * @param string $description Description of the argument
     */
    public function __construct(
        public string $graphqlType = '',
        public bool $list = false,
        public string $description = ''
    ) {
    }
}
