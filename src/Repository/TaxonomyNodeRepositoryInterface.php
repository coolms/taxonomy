<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Repository;

use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Core\Repository\RepositoryInterface;
use CoolMS\Rql\RqlRepositoryInterface;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;

interface TaxonomyNodeRepositoryInterface extends RepositoryInterface, RqlRepositoryInterface
{
    public function findBySlug(string $slug): ?TaxonomyNodeInterface;

    /**
     * @return TaxonomyNodeInterface[]
     */
    public function findAll(): array;

    /**
     * @return TaxonomyNodeInterface[]
     */
    public function findByTree(TaxonomyTreeInterface $tree): array;

    public function save(IdentifierProviderInterface $entity): void;

    public function delete(IdentifierProviderInterface $entity): void;
}
