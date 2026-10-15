<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Entity;

use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Core\Timestampable\UpdatedAtProviderInterface;
use CoolMS\Entity\LabelProviderInterface;

interface TaxonomyTreeInterface extends IdentifierProviderInterface, LabelProviderInterface, UpdatedAtProviderInterface
{
    public string $code {
        get;
    }
}
