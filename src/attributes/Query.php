<?php

namespace Jmsbrone\Graphql\attributes;

use Attribute;

/**
 * Attribute marks method as a GraphQL query.
 */
#[Attribute]
class Query extends ResolverTypeMethod
{

}
