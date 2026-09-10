<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Entity;

use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Entity\LabelProviderInterface;

interface TaxonomyTreeInterface extends IdentifierProviderInterface, LabelProviderInterface
{
    public string $code {
        get;
    }
}
