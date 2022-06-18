<?php

namespace Jmsbrone\Graphql\attributes;

use Attribute;

/**
 * Attribute marks method as a GraphQL mutation.
 */
#[Attribute]
class Mutation extends ResolverTypeMethod
{

}
