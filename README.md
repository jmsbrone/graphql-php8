# Overview

This package uses webonyx/graphql-php (https://webonyx.github.io/graphql-php/) and provides wrapper classes
around it to support defining GraphQL schema with php attributes.

## Notice

Please refer to the documentation provided for the base package to learn how to use it along with the current package
as the package does not provide additional ways of performing some operations, for example, defining types and creating
custom types must still be done according to the documentation of the base package.

# Usage

First, define the schema.

## Defining resolvers

```php
class MyResolver implements \Jmsbrone\Graphql\GraphQLResolver
{
    #[\Jmsbrone\Graphql\attributes\Query(
        'query1', 'myCustomType', nullable: true, description: 'Description for query named "query1"'
    )]
    // Method name is irrelevant, schema will use the name provided by the attribute
    public function myQuery(int $argument1, string $argument2 = null): mixed
    {
        // implementation...
    }
    
    // define types for this resolver
    public function getTypes(\Jmsbrone\Graphql\Loader $loader) : array
    {
        return [
            new \GraphQL\Type\Definition\ObjectType([
                'name' => 'myCustomType',
                'fields' => [
                    /*...*/
                ],
            ]),
            new \GraphQL\Type\Definition\ObjectType([
                'name' => 'customType2',
                // Lazy loading can be used to access custom types provided by other resolvers
                'fields' => function() use ($loader) {
                    'someField' => $loader->getTypeByName('someFieldType'),
                }
            ])
        ];
    }
}
```

## Defining custom types

Types are defined either in the resolver or on its own. For type definition refer
to the documentation https://webonyx.github.io/graphql-php/.

```php
// defining custom type 'someFieldType'
class someFieldType extends \GraphQL\Type\Definition\Type {}
```

## Defining DTO arguments

DTO arguments allow to collect multiple arguments into a single one automatically. First define a class for DTO.
All properties must be public.

```php
class DtoClassForMutation1 {
    public int $intArg;
    public string $stringArg;
    #[\Jmsbrone\Graphql\attributes\ArgType('someFieldType', list: true)]
    public mixed $customArgument;
}
```

Use it as follows

```php
class MyResolver 
{
    // ... definition from above
    
    #[\Jmsbrone\Graphql\attributes\Mutation('mutation1', 'Boolean')]
    public function mutation1(#[\Jmsbrone\Graphql\attributes\DtoArgument(DtoClassForMutation1::class)] DtoClassForMutation1 $dto): bool
    {
        // implementation
    }
}
```

## Schema

The definition up to this point will correspond to the following schema:

```graphql
type myCustomType {
    """
    type fields
    """
}

type someFieldType {
    """
    type fields
    """
}

type customType2 {
    someField: someFieldType
}

type Query {
    """
    Description for query named "query1"
    """
    query1(argument1: Int!, argument2: String): myCustomType
}

type Mutation {
    mutation1(intArg: Int!, stringArg: String!, customArgument: [someFieldType]): Boolean
}
```

## Using loader

Second, create the loader instance and register all resolvers and custom types in required order. Note that order
matters as types
provided by the resolvers will be registered in the same order as the resolvers.

```php
$loader = new \Jmsbrone\Graphql\Loader();
$loader->registerType(new someFieldType());
$loader->registerResolver(new MyResolver());
$loader->registerResolver(new MyResolver2());
```

## Using service

After all resolvers and types have been loaded, create the service instance to execute queries/mutations.

```php
$service = new \Jmsbrone\Graphql\Service($loader);
// set debug mode if required
$service->setDebugMode(true);

// retrieve query itself and variables for execution
$query = $_GET['query'];
$vars = $_GET['vars'];

echo json_encode($service->processQuery($query, $args));
```
