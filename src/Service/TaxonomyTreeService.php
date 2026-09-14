<?php

declare(strict_types=1);

namespace CoolMS\Taxonomy\Service;

use CoolMS\Core\Hierarchy\NestedSetOperatorInterface;
use CoolMS\Taxonomy\Entity\TaxonomyNodeInterface;
use CoolMS\Taxonomy\Entity\TaxonomyTreeInterface;
use CoolMS\Taxonomy\Repository\TaxonomyNodeRepositoryInterface;
use LogicException;

/**
 * Application-layer orchestrator for Taxonomy tree mutations.
 *
 * This class contains ZERO SQL. All index manipulation is delegated to
 * NestedSetOperatorInterface. This class only enforces domain invariants:
 *  - A node must have a tree assigned before insertion.
 *  - Parent and child must belong to the same tree.
 *  - Cross-tree moves are forbidden.
 *
 * Identity map synchronization: after calling shiftRight/shiftLeft/shiftSubtree
 * the ORM identity map is stale for modified rows. We call
 * $nodeRepository->refresh($parent) to re-read the actual DB state instead of
 * computing the expected diff manually.
 */
final readonly class TaxonomyTreeService implements TaxonomyTreeServiceInterface
{
    public function __construct(
        private NestedSetOperatorInterface $operator,
        private TaxonomyNodeRepositoryInterface $nodeRepository,
    ) {
    }

    public function insertNode(TaxonomyNodeInterface $node, ?TaxonomyNodeInterface $parent): void
    {
        if (null === $node->tree) {
            throw new LogicException('Node must have a tree assigned before insertion.');
        }

        if (null !== $parent
            && $parent->tree?->id->toRfc4122() !== $node->tree->id->toRfc4122()
        ) {
            throw new LogicException('Parent node must belong to the same tree.');
        }

        $treeId = $node->tree->id;

        $this->operator->transactional(function () use ($node, $parent, $treeId): void {
            $this->operator->lockTree($treeId);

            if (null === $parent) {
                // Root node: append after all existing nodes in the tree
                $maxRgt = $this->operator->getMaxRgt($treeId);
                $node->lft = $maxRgt + 1;
                $node->rgt = $maxRgt + 2;
                $node->level = 0;
                $node->parent = null;
            } else {
                // Child node: insert just before parent's rgt
                $this->operator->shiftRight($treeId, $parent->rgt, 2);
                $this->operator->shiftLeft($treeId, $parent->rgt - 1, 2);

                // Refresh to get updated rgt/lft from DB (DQL UPDATE bypasses identity map)
                $this->nodeRepository->refresh($parent);

                $node->lft = $parent->rgt - 2;
                $node->rgt = $parent->rgt - 1;
                $node->level = $parent->level + 1;
                $node->parent = $parent;
            }

            $this->nodeRepository->save($node);
        });
    }

    public function moveNode(TaxonomyNodeInterface $node, ?TaxonomyNodeInterface $newParent): void
    {
        if (null !== $newParent
            && $newParent->tree?->id->toRfc4122() !== $node->tree?->id->toRfc4122()
        ) {
            throw new LogicException('Cannot move a node across trees.');
        }

        if (null === $node->tree) {
            throw new LogicException('Node must have a tree assigned before moving.');
        }

        // A node cannot become a child of itself or of one of its own
        // descendants: in nested-set terms the destination would sit INSIDE
        // the moving subtree's [lft, rgt] range, which the negative-space
        // move algorithm below cannot represent and which would corrupt the
        // tree into a cycle. Same-tree is already established above, so the
        // ranges are directly comparable (`>=`/`<=` also rejects the
        // self-move where `$newParent === $node`).
        if (null !== $newParent
            && $newParent->lft >= $node->lft
            && $newParent->rgt <= $node->rgt
        ) {
            throw new LogicException('Cannot move a node into its own subtree.');
        }

        $treeId = $node->tree->id;
        $width = $node->rgt - $node->lft + 1;

        $this->operator->transactional(function () use ($node, $newParent, $treeId, $width): void {
            $this->operator->lockTree($treeId);

            // Step 1: Move subtree to negative space (avoids overlap during subsequent shifts)
            $negativeOffset = -($node->rgt + 1);
            $this->operator->shiftSubtree($treeId, $node->lft, $node->rgt, $negativeOffset);

            // Step 2: Close the gap left by the extracted subtree
            $this->operator->shiftRight($treeId, $node->rgt, -$width);
            $this->operator->shiftLeft($treeId, $node->rgt, -$width);

            // Step 3: Make room at the destination position
            if (null === $newParent) {
                $destRgt = $this->operator->getMaxRgt($treeId) + 1;
                $newLevel = 0;
            } else {
                // Step 2's gap-close shifted every boundary AFTER the extracted
                // subtree down by $width, via DQL UPDATEs that bypass the identity
                // map. When $newParent sat after the moved node its DB rgt is now
                // stale in memory, so re-read the committed row before siting the
                // destination (mirrors insertNode()'s refresh of $parent) --
                // otherwise a stale rgt places the subtree outside $newParent.
                // Step 2's gap-close shifted every boundary AFTER the extracted
                // subtree down by $width, via DQL UPDATEs that bypass the identity
                // map. When $newParent sat after the moved node its DB rgt is now
                // stale in memory, so re-read the committed row before siting the
                // destination (mirrors insertNode()'s refresh of $parent) --
                // otherwise a stale rgt places the subtree outside $newParent.
                $this->nodeRepository->refresh($newParent);
                $destRgt = $newParent->rgt;
                $newLevel = $newParent->level + 1;
            }
            // Open a $width-wide gap at $destRgt: every rgt >= $destRgt and every
            // lft > $destRgt-1 (i.e. lft >= $destRgt) moves up by $width. The rgt
            // threshold MUST be $destRgt (matching insertNode's shiftRight): the
            // old $destRgt-1 also bumped the destination parent's LAST child --
            // whose rgt is always $destRgt-1 -- collapsing two boundaries onto the
            // same integer and corrupting the nested set on every move.
            $this->operator->shiftRight($treeId, $destRgt, $width);
            $this->operator->shiftLeft($treeId, $destRgt - 1, $width);

            // Step 4: Move subtree from negative space to destination
            // Negative-space lft = node->lft + negativeOffset
            $negLft = $node->lft + $negativeOffset;
            $negRgt = $node->rgt + $negativeOffset;
            $moveOffset = $destRgt - $negLft;
            $levelOffset = $newLevel - $node->level;

            $this->operator->shiftSubtree($treeId, $negLft, $negRgt, $moveOffset);

            // Delegate level update to infrastructure operator (no-op when $levelOffset === 0)
            $this->operator->updateSubtreeLevel($treeId, $destRgt, $destRgt + $width - 1, $levelOffset);

            // Update adjacency list pointer and persist via repository
            $node->parent = $newParent;
            $this->nodeRepository->save($node);
        });

        // The lft/rgt/level above are rewritten by DQL UPDATEs on the operator, which
        // bypass the identity map; only $node->parent changed in-memory, so the entity
        // still carries its pre-move position. Re-read the committed row (done outside
        // the transaction so the flushed parent change is not discarded) so callers --
        // notably the API resource serializer -- observe the new position, not the old.
        $this->nodeRepository->refresh($node);
    }

    public function removeNode(TaxonomyNodeInterface $node): void
    {
        if (null === $node->tree) {
            throw new LogicException('Node must have a tree assigned before removal.');
        }

        $treeId = $node->tree->id;
        $lft = $node->lft;
        $rgt = $node->rgt;
        $width = $rgt - $lft + 1;

        $this->operator->transactional(function () use ($treeId, $lft, $rgt, $width): void {
            $this->operator->lockTree($treeId);
            $this->operator->deleteSubtree($treeId, $lft, $rgt);
            $this->operator->shiftRight($treeId, $rgt, -$width);
            $this->operator->shiftLeft($treeId, $rgt, -$width);
        });
    }

    public function rebuildTree(TaxonomyTreeInterface $tree): void
    {
        $allNodes = $this->nodeRepository->findByTree($tree);
        $roots = array_filter($allNodes, static fn (TaxonomyNodeInterface $n) => null === $n->parent);

        $this->operator->transactional(function () use ($tree, $roots): void {
            $this->operator->lockTree($tree->id);
            foreach ($roots as $root) {
                $this->operator->rebuildIndexes($root);
            }
        });
    }
}
