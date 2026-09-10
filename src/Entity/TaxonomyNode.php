<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Entity;

use CoolMS\Core\Attribute\ClassMeta;
use CoolMS\Core\Identifier\IdentifierProviderTrait;
use CoolMS\Core\Timestampable\TimestampableTrait;
use CoolMS\Entity\Attribute\DiscriminatorValue;
use CoolMS\Entity\Traits\LabelProviderTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * A node in a taxonomy tree, representing a category or classification entry.
 */
#[ClassMeta(label: 'Taxonomy Node')]
#[DiscriminatorValue('taxonomy_node')]
class TaxonomyNode implements TaxonomyNodeInterface
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

    /**
     * Machine-readable slug shape: a lowercase letter, then lowercase letters,
     * digits or hyphens.
     *
     * !! THE SHAPE IS DELIBERATELY THE ENTITY-ALIAS SHAPE, so a taxonomy slug is
     * a valid entity alias by construction. A consumer that subclasses a node
     * into an entity type reads the slug as that type's alias, and this pattern
     * is what makes the two interchangeable without a translation step.
     * Narrowing it breaks that consumer silently.
     *
     * The consumer is not named on purpose: this is a published package and its
     * docblocks ship with it.
     */
    public const string SLUG_PATTERN = '/^[a-z][a-z0-9-]+$/';

    public bool $isRoot {
        get => null === $this->parent;
    }
    public string $type {
        get => 'taxonomy_node';
    }

    /**
     * Direct children -- covariant narrowing of NestedSetNodeInterface::$children,
     * which declares `iterable`.
     *
     * Declared as `Collection` with `private(set)` so that:
     *  - Consumers can use the Collection API without an instanceof guard
     *  - Doctrine can hydrate the backing store via reflection (bypasses private(set))
     *  - External code cannot replace the collection directly
     *
     * `doctrine/collections` is a data structure, not the ORM. A domain package
     * may depend on it; it may not import `Doctrine\ORM\` or `Doctrine\DBAL\`.
     * {@see TaxonomyTree::$nodes} is the same choice, made in this package before
     * this one, and `doctrine/collections` is already in its manifest.
     *
     * The value type is TaxonomyNodeInterface, NOT `static`: under the CTI
     * discriminator chain a DynamicEntityType node can hold a plain
     * TaxonomyNode child, so "all children are the same concrete class" is not
     * true of this tree. The runtime filter in
     * DynamicEntityTypeRepository::findChildren() is the code that already
     * knew that.
     *
     * @var Collection<int, TaxonomyNodeInterface>
     */
    public private(set) Collection $children {
        get => $this->children;
    }

    /**
     * Snapshot of the tree id taken on a load -- used to detect tree changes on update.
     */
    private ?string $originalTreeId = null;

    public function __construct(
        string $label = '',
        /**
         * Unique machine-readable slug (used as entityAlias for DynamicEntityType).
         */
        public string $slug = '',
        /**
         * The tree this node belongs to (tree ownership). Immutable after creation (first persist).
         */
        public ?TaxonomyTreeInterface $tree = null {
            get => $this->tree;
            // set => null === $this->slug ? $value : throw new ImmutablePropertyException(__PROPERTY__, static::class);
        },
        /**
         * Adjacency list (parent pointer).
         */
        public ?TaxonomyNodeInterface $parent = null,

        /**
         * Left boundary of the nested set range.
         */
        public int $lft = 0,

        /**
         * Right boundary of the nested set range.
         */
        public int $rgt = 0,

        /**
         * Depth level in the tree (root = 0).
         */
        public int $level = 0,
    ) {
        $this->__identityConstruct(Uuid::v7());
        $this->__labelConstruct($label);
        $this->__timestampableConstruct();
        $this->children = new ArrayCollection();
    }

    /**
     * Whether `$slug` is a well-formed taxonomy-node slug ({@see SLUG_PATTERN}).
     * The write-side (create/update processors) rejects a malformed slug up front
     * so a broken machine key never reaches the DB (where it is indexed + unique
     * and used as a lookup key / DynamicEntityType alias).
     */
    public static function isValidSlug(string $slug): bool
    {
        return 1 === preg_match(self::SLUG_PATTERN, $slug);
    }

    public function onPostLoad(): void
    {
        $this->originalTreeId = $this->tree?->id->toRfc4122();
    }

    public function onPreUpdate(): void
    {
        $currentTreeId = $this->tree?->id->toRfc4122();
        if (null !== $this->originalTreeId && $this->originalTreeId !== $currentTreeId) {
            throw new LogicException(sprintf('tree_id is immutable after node creation (node %s).', $this->id->toRfc4122()));
        }
    }
}
