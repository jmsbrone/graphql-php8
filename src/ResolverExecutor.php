<?php

namespace Jmsbrone\Graphql;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Jmsbrone\Graphql\attributes\ArgType;
use Jmsbrone\Graphql\attributes\DtoArgument;
use Jmsbrone\Graphql\attributes\Mutation;
use Jmsbrone\Graphql\attributes\Query;
use Jmsbrone\Graphql\attributes\ResolverTypeMethod;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Executor for graphql resolvers.
 * Manages the parsing of resolver attributes and extracting graphql schema information from them.
 */
class ResolverExecutor
{
    /** @var ReflectionClass|null Created reflection instance of the resolver */
    protected ReflectionClass|null $reflection = null;

    /**
     * Creates new executor for given resolver.
     *
     * @param GraphQLResolver $resolver Resolver instance to use
     * @param Loader $loader Graphql loader instance
     */
    public function __construct(private GraphQLResolver $resolver, protected Loader $loader)
    {
        foreach ($this->resolver->getTypes($loader) as $type) {
            $this->loader->registerType($type);
        }
    }

    /**
     * Returns queries of the resolver.
     *
     * @return array List of queries provided by the resolver
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    public function getQueries(): array
    {
        return $this->getQueryTypesByAttribute(Query::class);
    }

    /**
     * Returns mutations of the resolver.
     *
     * @return array List of mutations provided by the resolver
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    public function getMutations(): array
    {
        return $this->getQueryTypesByAttribute(Mutation::class);
    }

    /**
     * Returns queries or mutations depending on given attribute class.
     *
     * @param string $class Attribute class to analyze
     * @return array List of types provided by the resolver that correspond
     * to checked attribute class
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    protected function getQueryTypesByAttribute(string $class): array
    {
        $queries = [];
        $reflection = $this->getResolverReflection();
        foreach ($reflection->getMethods() as $method) {
            /** @var Query $attribute */
            $attribute = ReflectionHelper::getMethodAttribute($method, $class);
            if ($attribute === null) {
                continue;
            }

            $queryArgs = [];
            $dtoMap = [];
            $methodArgs = $method->getParameters();
            foreach ($methodArgs as $methodArg) {
                $this->collectGraphQLDataFromMethodArgument($methodArg, $queryArgs, $dtoMap);
            }

            $queries[$attribute->name] = [
                'type' => $this->inferReturnType($attribute),
                'args' => $queryArgs,
                'description' => $attribute->description,
                'resolve' => $this->getGraphQLResolveHandler($method, $queryArgs, $dtoMap),
            ];
        }

        return $queries;
    }

    /**
     * Returns graphql type corresponding to method's return type.
     *
     * @param ResolverTypeMethod $attribute Method's attribute to check
     * @return ObjectType|Type GraphQL type corresponding to method return type
     * @throws exceptions\TypeNotFoundException
     */
    protected function inferReturnType(ResolverTypeMethod $attribute): ObjectType|Type
    {
        $type = $this->loader->getTypeByName($attribute->returnType);
        if ($attribute->list) {
            $type = Type::listOf($type);
        }
        if (!$attribute->nullable) {
            $type = Type::nonNull($type);
        }

        return $type;
    }

    /**
     * Returns a callable to act as an essential graphql resolve method for given method.
     *
     * @param ReflectionMethod $method Method to use as the resolve function
     * @param array $queryArgs Arguments that will be passed to the method
     * @param array $dtoMap Information with what arguments to combine into a DTO instance
     * @return callable
     */
    protected function getGraphQLResolveHandler(ReflectionMethod $method, array $queryArgs, array $dtoMap): callable
    {
        return function ($objectValue, array $args) use ($method, $queryArgs, $dtoMap) {
            foreach ($dtoMap as $dtoConfig) {
                $dto = new $dtoConfig['class']();
                foreach ($dtoConfig['args'] as $argName) {
                    $dto->$argName = $args[$argName] ?? null;
                    unset($args[$argName]);
                }
                $args[$dtoConfig['name']] = $dto;
            }

            return call_user_func([$this->resolver, $method->name], ...$args);
        };
    }

    /**
     * Returns appropriate graphql type for given reflection type (php type).
     *
     * @param ReflectionUnionType|ReflectionType|ReflectionNamedType $reflectionType Reflection type that represents
     * php type
     * @param ArgType|null $typeAttribute (optional) Attribute type that can override type inferred from php type
     * @return Type Determined graphql type
     * @throws exceptions\TypeNotFoundException
     */
    protected function inferGraphQLTypeByAttribute(
        ReflectionType|ReflectionNamedType|ReflectionUnionType $reflectionType,
        ?ArgType $typeAttribute
    ): Type {
        if ($typeAttribute !== null && !empty($typeAttribute->graphqlType)) {
            $dataType = $typeAttribute->graphqlType;
        } else {
            $dataType = $reflectionType->getName();
        }

        $graphqlType = $this->loader->getTypeByName($dataType);

        if ($typeAttribute !== null && $typeAttribute->list) {
            $graphqlType = Type::listOf($graphqlType);
        }

        if (!$reflectionType->allowsNull()) {
            $graphqlType = Type::nonNull($graphqlType);
        }

        return $graphqlType;
    }

    /**
     * Collects graphql related data from method argument.
     * The function will write data to provided by reference arguments.
     *
     * @param ReflectionParameter $methodArg Method argument reflection instance
     * @param array $queryArgs Reference to the array collecting arguments for the method
     * @param array $dtoMap Reference to array collecting dto arguments for the method
     * @return void
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    protected function collectGraphQLDataFromMethodArgument(ReflectionParameter $methodArg, array &$queryArgs, array &$dtoMap): void
    {
        /** @var DtoArgument $dtoArgument */
        $dtoArgument = ReflectionHelper::getArgumentAttribute($methodArg, DtoArgument::class);
        $dtoClass = $dtoArgument?->dtoClass;
        if (!empty($dtoClass)) {
            $this->processDtoArgument($dtoClass, $methodArg, $queryArgs, $dtoMap);
        } else {
            /** @var ArgType $typeAttribute */
            $typeAttribute = ReflectionHelper::getArgumentAttribute($methodArg, ArgType::class);

            $graphqlType = $this->inferGraphQLTypeByAttribute($methodArg->getType(), $typeAttribute);
            if ($methodArg->isOptional()) {
                $graphqlType = Type::getNullableType($graphqlType);
            }
            $queryArgs[$methodArg->name] = [
                'type' => $graphqlType,
                'description' => $typeAttribute?->description,
            ];
        }
    }

    /**
     * Collects arguments to be combined into a dto.
     *
     * @param string $dtoClass Class of the checked dto
     * @param ReflectionParameter $methodArg Method argument reflection instance
     * @param array $queryArgs Reference to the array collecting arguments for the method
     * @param array $dtoMap Reference to array collecting dto arguments for the method
     * @return void
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    protected function processDtoArgument(string $dtoClass, ReflectionParameter $methodArg, array &$queryArgs, array &$dtoMap): void
    {
        $dtoArgs = [];
        $dtoReflection = new ReflectionClass($dtoClass);
        foreach ($dtoReflection->getProperties() as $dtoField) {
            $argumentName = $dtoField->name;
            /** @var ArgType $typeAttribute */
            $typeAttribute = ReflectionHelper::getPropertyAttribute($dtoField, ArgType::class);

            $queryArgs[$argumentName] = [
                'type' => $this->inferGraphQLTypeByAttribute($dtoField->getType(), $typeAttribute),
                'description' => $typeAttribute?->description,
            ];
            $dtoArgs[] = $argumentName;
        }

        $dtoMap[] = [
            'class' => $dtoClass,
            'args' => $dtoArgs,
            'name' => $methodArg->name
        ];
    }

    /**
     * Returns reflection instance for the used resolver.
     *
     * @return ReflectionClass
     */
    protected function getResolverReflection(): ReflectionClass
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionClass($this->resolver);
        }

        return $this->reflection;
    }
}
