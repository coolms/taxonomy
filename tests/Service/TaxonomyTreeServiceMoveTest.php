<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Tests\Service;

use CoolMS\Core\Hierarchy\NestedSetOperatorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyNode;
use CoolMS\Taxonomy\Entity\TaxonomyTree;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use CoolMS\Taxonomy\Service\TaxonomyTreeService;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the "stale level after move" bug.
 *
 * moveNode() rewrites lft/rgt/level through DQL UPDATEs on the operator, which
 * bypass the ORM identity map. Only $node->parent is changed in-memory, so the
 * change-set written by save() is just parent_id -- the DB ends up correct, but
 * the in-memory entity still carries its pre-move lft/rgt/level. The API resource
 * is serialized from that same entity right after the move, so the PUT/PATCH
 * response previously reported the OLD position (e.g. level: 1 for a node that
 * was just promoted to a root, level: 0).
 *
 * The fix re-reads the committed row via the repository so the entity reflects
 * persisted state before it is serialized.
 */
final class TaxonomyTreeServiceMoveTest extends TestCase
{
    public function testMoveToRootRefreshesEntityToPersistedPosition(): void
    {
        $tree = new TaxonomyTree('Categories', 'categories');

        // Tree before the move: root(1..4) { child(2..3) }.
        $root = new TaxonomyNode('Root', 'root', $tree, null, 1, 4, 0);
        $child = new TaxonomyNode('Child', 'child', $tree, $root, 2, 3, 1);

        // Committed DB state after promoting `child` to a sibling root:
        // root collapses to (1..2), child lands at (3..4), level 0.
        $persisted = ['lft' => 3, 'rgt' => 4, 'level' => 0];

        $operator = $this->createStub(NestedSetOperatorInterface::class);
        // Run the closure synchronously; the DQL mutations are no-ops here because
        // their effect lives in the DB, not on the in-memory entity -- which is the
        // whole point of the bug.
        $operator->method('transactional')->willReturnCallback(static fn (callable $fn) => $fn());
        $operator->method('getMaxRgt')->willReturn(2); // max rgt once the subtree is extracted

        $repository = $this->createStub(TaxonomyNodeRepositoryInterface::class);
        // refresh() must mirror what Doctrine's EntityManager::refresh() does:
        // re-hydrate the entity from the committed row.
        $repository->method('refresh')->willReturnCallback(
            static function (TaxonomyNode $entity) use ($persisted): void {
                $entity->lft = $persisted['lft'];
                $entity->rgt = $persisted['rgt'];
                $entity->level = $persisted['level'];
            },
        );

        $service = new TaxonomyTreeService($operator, $repository);
        $service->moveNode($child, null);

        self::assertSame(0, $child->level, 'moved node level must reflect persisted DB state, not the pre-move value');
        self::assertSame(3, $child->lft, 'moved node lft must reflect persisted DB state');
        self::assertSame(4, $child->rgt, 'moved node rgt must reflect persisted DB state');
        self::assertNull($child->parent, 'moved-to-root node must have no parent');
        self::assertTrue($child->isRoot);
    }

    public function testMoveIntoSelfIsRejected(): void
    {
        $tree = new TaxonomyTree('Categories', 'categories');
        $node = new TaxonomyNode('Node', 'node', $tree, null, 1, 2, 0);

        $service = new TaxonomyTreeService(
            $this->createStub(NestedSetOperatorInterface::class),
            $this->createStub(TaxonomyNodeRepositoryInterface::class),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot move a node into its own subtree.');
        $service->moveNode($node, $node);
    }

    public function testMoveIntoOwnDescendantIsRejected(): void
    {
        $tree = new TaxonomyTree('Categories', 'categories');
        // root(1..4) { child(2..3) } -- moving root under its own child is a cycle.
        $root = new TaxonomyNode('Root', 'root', $tree, null, 1, 4, 0);
        $child = new TaxonomyNode('Child', 'child', $tree, $root, 2, 3, 1);

        $service = new TaxonomyTreeService(
            $this->createStub(NestedSetOperatorInterface::class),
            $this->createStub(TaxonomyNodeRepositoryInterface::class),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot move a node into its own subtree.');
        $service->moveNode($root, $child);
    }

    public function testLegalMoveToUnrelatedParentIsAllowed(): void
    {
        $tree = new TaxonomyTree('Categories', 'categories');
        // root(1..6) { a(2..3), b(4..5) } -- moving sibling `a` under sibling `b`
        // is legal: `b` is NOT inside `a`'s [lft, rgt] range, so the guard
        // must NOT fire.
        $root = new TaxonomyNode('Root', 'root', $tree, null, 1, 6, 0);
        $a = new TaxonomyNode('A', 'a', $tree, $root, 2, 3, 1);
        $b = new TaxonomyNode('B', 'b', $tree, $root, 4, 5, 1);

        $operator = $this->createStub(NestedSetOperatorInterface::class);
        $operator->method('transactional')->willReturnCallback(static fn (callable $fn) => $fn());

        $service = new TaxonomyTreeService(
            $operator,
            $this->createStub(TaxonomyNodeRepositoryInterface::class),
        );

        $service->moveNode($a, $b);

        self::assertSame($b, $a->parent, 'a legal move must set the new parent');
    }
}
