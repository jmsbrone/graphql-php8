<?php

namespace Jmsbrone\Graphql;

use GraphQL\Type\Definition\Type;

/**
 * Resolvers define types and queries/mutations for working with those types.
 */
interface GraphQLResolver
{
    /**
     * Returns types provided by this resolver.
     *
     * @return Type[]
     */
    public function getTypes(Loader $loader): array;
}
