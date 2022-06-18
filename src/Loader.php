<?php

namespace Jmsbrone\Graphql;

use GraphQL\Type\Definition\NullableType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Jmsbrone\Graphql\exceptions\TypeNotFoundException;
use ReflectionException;

/**
 * Responsible for loading graphql elements (query/mutation/type) and providing
 * methods for accessing them during schema building.
 */
class Loader
{
    /** @var Type[] Cached loaded types */
    protected array $typeCache = [];

    /** @var ResolverExecutor[] List of resolver executors */
    protected array $resolverExecutors = [];

    /**
     * Registers given resolver in the loader.
     * Schema can only be built from registered resolvers.
     *
     * @param GraphQLResolver $resolver Resolver instance to register
     * @return void
     */
    public function registerResolver(GraphQLResolver $resolver): void
    {
        $this->resolverExecutors[] = new ResolverExecutor($resolver, $this);
    }

    /**
     * Registers a custom type.
     *
     * @param Type $type Custom type instance to register
     * @return void
     */
    public function registerType(Type $type): void
    {
        $this->typeCache[$type->name] = $type;
    }

    /**
     * Returns graphql type object by its name.
     *
     * @param string $name Name of the type to retrieve. Must be either built-in or name it was registered with
     * @return Type|NullableType
     * @throws TypeNotFoundException
     */
    public function getTypeByName(string $name): Type|NullableType
    {
        $type = match ($name) {
            'string', Type::STRING => Type::string(),
            'int', Type::INT => Type::int(),
            'bool', Type::BOOLEAN => Type::boolean(),
            'float', Type::FLOAT => Type::float(),
            default => null,
        };

        if (empty($type)) {
            if (!isset($this->typeCache[$name])) {
                throw new TypeNotFoundException($name);
            } else {
                $type = $this->typeCache[$name];
            }
        }

        return $type;
    }

    /**
     * Returns root query type.
     *
     * @return ObjectType
     */
    public function getRootQuery(): ObjectType
    {
        return new ObjectType([
            'name' => 'Query',
            'fields' => fn() => [
                ...$this->getQueriesFromRegisteredResolvers(),
            ],
            'types' => [
                ...$this->typeCache,
            ],
        ]);
    }

    /**
     * Returns root mutation type.
     *
     * @return ObjectType
     * @throws ReflectionException
     * @throws TypeNotFoundException
     */
    public function getRootMutation(): ObjectType
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                ...$this->getMutationsFromRegisteredResolvers(),
            ],
        ]);
    }

    /**
     * Returns data from registered resolvers.
     * Data from each resolver is retrieved via given callback.
     *
     * @param callable $handler The function that will run on each registered ResolverExecutor.
     * Must return the required data depending on what is being collected.
     * @return array
     */
    protected function collectFromResolvers(callable $handler): array
    {
        $result = [];
        foreach ($this->resolverExecutors as $executor) {
            $extractedData = $handler($executor);
            if (!is_array($extractedData)) {
                $extractedData = [$extractedData];
            }
            $result = array_merge($result, $extractedData);
        }

        return $result;
    }

    /**
     * Returns all registered queries.
     *
     * @return array
     * @throws ReflectionException
     * @throws TypeNotFoundException
     */
    protected function getQueriesFromRegisteredResolvers(): array
    {
        return $this->collectFromResolvers(fn(ResolverExecutor $executor) => $executor->getQueries());
    }

    /**
     * Returns all registered mutations.
     *
     * @return array
     * @throws ReflectionException
     * @throws TypeNotFoundException
     */
    protected function getMutationsFromRegisteredResolvers(): array
    {
        return $this->collectFromResolvers(fn(ResolverExecutor $executor) => $executor->getMutations());
    }
}
