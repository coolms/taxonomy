<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Entity;

use CoolMS\Core\Attribute\ClassMeta;
use CoolMS\Core\Identifier\IdentifierProviderTrait;
use CoolMS\Core\Timestampable\TimestampableTrait;
use CoolMS\Entity\Traits\LabelProviderTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

/**
 * A named tree that groups taxonomy nodes into a hierarchical classification.
 */
#[ClassMeta(label: 'Taxonomy Tree')]
class TaxonomyTree implements TaxonomyTreeInterface
{
    use IdentifierProviderTrait {
        IdentifierProviderTrait::__construct as private __identityConstruct;
    }
    use LabelProviderTrait {
        LabelProviderTrait::__construct as private __labelConstruct;
    }
    use TimestampableTrait {
        TimestampableTrait::__construct as private __timestampableConstruct;
    }

    public function __construct(
        string $label = '',
        /**
         * Unique machine-readable code (e.g., 'dynamic_entity_types', 'catalog').
         * Immutable after creation.
         */
        public string $code = '',
        /** @var Collection<int, TaxonomyNodeInterface> */
        public ?Collection $nodes = null,
    ) {
        $this->__identityConstruct(Uuid::v7());
        $this->__labelConstruct($label);
        $this->__timestampableConstruct();
        $this->nodes ??= new ArrayCollection();
    }
}
