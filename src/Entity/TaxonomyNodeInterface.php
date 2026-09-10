<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Entity;

use CoolMS\Core\Hierarchy\NestedSetNodeInterface;
use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Entity\LabelProviderInterface;
use Doctrine\Common\Collections\Collection;

interface TaxonomyNodeInterface extends IdentifierProviderInterface, LabelProviderInterface, NestedSetNodeInterface
{
    public string $slug {
        get;
    }

    /**
     * Covariant narrowing of NestedSetNodeInterface::$parent { get; set; }.
     * PHP 8.4+ allows child interfaces to redeclare property hooks
     * with a narrower (subtype) return type.
     */
    public ?TaxonomyNodeInterface $parent {
        get;
        set;
    }

    /**
     * Covariant narrowing of NestedSetNodeInterface::$children { get; }, which
     * declares `iterable`. Collection is Traversable, so this is a subtype and
     * PHP 8.4+ permits the redeclaration -- the same move as $parent above.
     *
     * Narrowed HERE, not only on the concrete class, because the consumer that
     * needs it is typed on this interface: without the narrowing,
     * DynamicEntityTypeRepository::findChildren() must keep an
     * `instanceof Collection` guard to reach toArray().
     *
     * @var Collection<int, TaxonomyNodeInterface>
     */
    // NestedSetNodeInterface declares `iterable<int, static>` -- a claim that a
    // node's children are all the same concrete class, which the CTI chain does
    // not honour. The suppression marks that disagreement rather than restating
    // the untrue `static` here to satisfy the checker.
    // @phpstan-ignore property.phpDocType
    public Collection $children {
        get;
    }
    public ?TaxonomyTreeInterface $tree {
        get;
    }
    public bool $isRoot {
        get;
    }

    /**
     * Returns the Doctrine discriminator value for this node type.
     * Matches the value declared via #[DiscriminatorValue('...')].
     */
    public string $type {
        get;
    }
}
