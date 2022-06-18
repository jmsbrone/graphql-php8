<?php

namespace Jmsbrone\Graphql;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use ReflectionException;

/**
 * Service for working with graphql.
 * Provides methods for initialization
 */
class Service
{
    /** @var Schema|null Internal graphql schema object */
    protected Schema|null $schema = null;

    /** @var bool Debug mode status */
    private bool $debugMode = false;

    /**
     * Creates new service instance for working with GraphQL.
     * Passed loader object must already be initialized with all required
     * queries/mutations/types as the schema will be built upon creation
     * of the service.
     *
     * @param Loader $loader Loader instance to use
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    public function __construct(protected Loader $loader)
    {
        $this->init();
    }

    /**
     * Executes given query/mutation with arguments.
     *
     * @param string $query Query/mutation to execute
     * @param array|null $variables Array with query/mutation arguments
     * @return array
     */
    public function processQuery(string $query, array $variables = null): array
    {
        $result = GraphQL::executeQuery($this->schema, $query, [], null, $variables);

        return $result->toArray($this->debugMode ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE : null);
    }

    /**
     * Turns debug mode on/off.
     *
     * @param bool $value
     * @return void
     */
    public function setDebugMode(bool $value): void
    {
        $this->debugMode = $value;
    }

    /**
     * Returns created schema instance.
     *
     * @return Schema|null
     */
    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    /**
     * Returns internal loader instance.
     *
     * @return Loader
     */
    public function getLoader(): Loader
    {
        return $this->loader;
    }

    /**
     * Initializes the service.
     *
     * @return void
     * @throws ReflectionException
     * @throws exceptions\TypeNotFoundException
     */
    protected function init(): void
    {
        $this->schema = new Schema(SchemaConfig::create()
            ->setQuery($this->loader->getRootQuery())
            ->setMutation($this->loader->getRootMutation())
            ->setTypeLoader([$this->loader, 'getTypeByName'])
        );
    }
}
