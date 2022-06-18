<?php

namespace Jmsbrone\Graphql\attributes;

use Attribute;

/**
 * Attribute that is supposed to be put on resolver arguments in order to allow usage of dto classes (containers).
 * This attribute basically merges several individual arguments together in the provided dto class.
 */
#[Attribute]
class DtoArgument
{
    /**
     * @param string $dtoClass Dto class name
     */
    public function __construct(public string $dtoClass)
    {
    }
}
