<?php

declare(strict_types=1);

namespace Orion\Specs\Builders\Components;

use Doctrine\DBAL\Schema\Column;
use Orion\ValueObjects\Specs\Schema\SchemaProperty;

class PropertyBuilder
{
    /**
     * @param Column $column
     * @param string|SchemaProperty $concretePropertyClass
     *
     * @return SchemaProperty
     */
    public function build(Column $column, string $concretePropertyClass): SchemaProperty
    {
        /** @var SchemaProperty $property */
        $property = new $concretePropertyClass();
        $property->name = $column->getName();

        return $property;
    }
}