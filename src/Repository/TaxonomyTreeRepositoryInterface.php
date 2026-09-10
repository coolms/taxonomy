<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Repository;

use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Core\Identifier\IdentifierProviderInterface;
use CoolMS\Core\Repository\RepositoryInterface;
use CoolMS\Rql\RqlRepositoryInterface;

interface TaxonomyTreeRepositoryInterface extends RepositoryInterface, RqlRepositoryInterface
{
    // NOTE: find(Uuid $id) is intentionally NOT declared here.
    // ServiceEntityRepository::find(mixed $id, ...) satisfies RepositoryInterface::find(Uuid $id)
    // via PHP's contravariance rule. Redeclaring it with a narrower Uuid type would cause a
    // Fatal Error (declaration incompatibility with Doctrine's wider mixed signature).

    public function findByCode(string $code): ?TaxonomyTreeInterface;

    /**
     * @return TaxonomyTreeInterface[]
     */
    public function findAll(): array;

    /**
     * Count taxonomy nodes attached to the given tree without hydrating
     * the full collection. Used by deletion guards and similar predicates
     * that must avoid loading every child row.
     */
    public function countNodes(TaxonomyTreeInterface $tree): int;

    public function save(IdentifierProviderInterface $entity): void;

    public function delete(IdentifierProviderInterface $entity): void;
}
